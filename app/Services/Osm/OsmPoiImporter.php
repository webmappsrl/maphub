<?php

declare(strict_types=1);

namespace App\Services\Osm;

use App\Dto\OsmNodePoiData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Wm\WmPackage\Exceptions\OsmClientException;
use Wm\WmPackage\Exceptions\OsmClientExceptionNoTags;
use Wm\WmPackage\Http\Clients\OsmClient;
use Wm\WmPackage\Models\EcPoi;
use Wm\WmPackage\Models\TaxonomyPoiType;

/**
 * Servizio di alto livello: dato un set di OSMID di tipo `node`,
 * scarica i dati da OpenStreetMap (via {@see OsmClient}), li normalizza in {@see OsmNodePoiData}
 * e crea/aggiorna gli {@see EcPoi} associando il {@see TaxonomyPoiType} corretto.
 * Testi lunghi (description, excerpt da inscription) restano nel JSON {@see EcPoi::$properties}
 * perché lo schema DB del progetto non espone colonne dedicate per quelle chiavi.
 *
 * Modalità dry-run: nessuna persistenza, ritorna l'esito atteso utile per validazione interattiva (Nova/CLI).
 */
class OsmPoiImporter
{
    public function __construct(
        private readonly OsmClient $osmClient,
        private readonly OsmTaxonomyPoiTypeResolver $taxonomyResolver,
    ) {}

    /**
     * Importa una lista di OSMID (node).
     *
     * @param  list<int|string>  $osmIds  Identificativi numerici dei node OSM (verranno castati a int).
     * @param  int  $appId  ID dell'App di destinazione (campo obbligatorio sullo schema `ec_pois`).
     * @param  int|null  $userId  Utente proprietario (opzionale).
     * @param  bool  $dryRun  Se true non persiste nulla.
     * @param  bool  $global  Se la colonna `ec_pois.global` esiste: valore da impostare (true = inclusi in {@see \Wm\WmPackage\Models\App::getAllPoisGeojson()} / pois.geojson).
     */
    public function importNodes(array $osmIds, int $appId, ?int $userId = null, bool $dryRun = false, bool $global = true): ImportReport
    {
        $report = new ImportReport($dryRun);
        $hasGlobalColumn = Schema::hasColumn((new EcPoi)->getTable(), 'global');

        $ids = $this->normalizeIds($osmIds);
        $maxPerRun = (int) config('osm-import.max_ids_per_run', 0);
        if ($maxPerRun > 0 && count($ids) > $maxPerRun) {
            $report->setTruncatedBeyondLimit(count($ids) - $maxPerRun);
            $ids = array_slice($ids, 0, $maxPerRun);
        }

        $delayMs = max(0, (int) config('osm-import.request_delay_ms', 0));
        $total = count($ids);

        foreach ($ids as $index => $osmid) {
            try {
                $report->addOutcome($this->importSingleNode($osmid, $appId, $userId, $dryRun, $global, $hasGlobalColumn));
            } catch (\Throwable $e) {
                [$category, $message] = $this->classifyFailure($osmid, $e);
                Log::warning('OsmPoiImporter: failure on node '.$osmid, [
                    'category' => $category,
                    'message' => $e->getMessage(),
                    'exception' => $e::class,
                ]);
                $report->addFailure($osmid, $message, $category);
            }

            if ($delayMs > 0 && $index < $total - 1) {
                usleep($delayMs * 1000);
            }
        }

        return $report;
    }

    /**
     * Trasforma un'eccezione (anche TypeError dovuto a body non-JSON da OSM) in una coppia
     * (categoria, messaggio in italiano) leggibile per l'utente.
     *
     * @return array{0: string, 1: string}
     */
    private function classifyFailure(int $osmid, \Throwable $e): array
    {
        if ($e instanceof OsmClientExceptionNoTags) {
            return ['no_tags', "node/{$osmid}: nessun tag su OpenStreetMap, niente da importare."];
        }
        $message = $e->getMessage();
        if ($this->looksLikeStorageMisconfiguration($message)) {
            return [
                'storage',
                "node/{$osmid}: dopo il salvataggio l'observer ha tentato di aggiornare pois.geojson su S3/MinIO (disk wmfe) ma la configurazione è incompleta. Imposta almeno AWS_DEFAULT_REGION (es. us-east-1 o eu-central-1), AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY e, in locale, AWS_ENDPOINT per MinIO. Vedi .env-example.",
            ];
        }
        if ($e instanceof \InvalidArgumentException) {
            if (str_contains($message, 'Point') || str_contains($message, 'geometry')) {
                return ['geometry', "node/{$osmid}: geometria OSM non valida."];
            }

            return ['other', "node/{$osmid}: {$message}"];
        }
        if ($e instanceof OsmClientException) {
            return ['not_found_or_invalid_osm', "node/{$osmid}: ".$e->getMessage()];
        }
        if ($e instanceof \TypeError) {
            // Tipico: api.openstreetmap.org risponde con body non JSON (404/410 o errore intermittente).
            return ['not_found_or_invalid_osm', "node/{$osmid}: non trovato su OpenStreetMap o risposta non valida."];
        }

        return ['other', "node/{$osmid}: ".$message];
    }

    /**
     * Dopo un import reale, EcPoiObserver lancia job/side-effect che usano il disk `wmfe` (S3).
     * Senza region/credenziali l'AWS SDK lancia InvalidArgumentException: non va confuso con la geometria OSM.
     */
    private function looksLikeStorageMisconfiguration(string $message): bool
    {
        $lower = mb_strtolower($message);

        return (str_contains($lower, 'region') && str_contains($lower, 's3'))
            || str_contains($lower, 'wmfe')
            || str_contains($lower, 'failed to get disk')
            || str_contains($lower, 'filesystemmanager')
            || (str_contains($lower, 'aws') && str_contains($lower, 'configuration'));
    }

    /**
     * @return array{action: string, osmid: int, ec_poi_id: ?int, taxonomy_identifier: ?string, taxonomy_created: bool}
     */
    private function importSingleNode(int $osmid, int $appId, ?int $userId, bool $dryRun, bool $global, bool $hasGlobalColumn): array
    {
        [$properties, $geometry] = $this->fetchOsmNode($osmid);
        $dto = OsmNodePoiData::fromOsmNode($osmid, $properties, $geometry);

        $resolution = $this->taxonomyResolver->resolve($dto, $dryRun);
        $taxonomy = $resolution['taxonomy'];

        $existing = $this->findExistingEcPoiByOsmid($osmid);
        $action = $existing ? 'updated' : 'created';

        if ($dryRun) {
            return [
                'action' => $action,
                'osmid' => $osmid,
                'ec_poi_id' => $existing?->id,
                'taxonomy_identifier' => $taxonomy->identifier,
                'taxonomy_created' => $resolution['created'],
            ];
        }

        $ecPoi = DB::transaction(function () use ($dto, $existing, $appId, $userId, $taxonomy, $global, $hasGlobalColumn) {
            $attrs = $dto->toEcPoiAttributes($appId, $userId);
            unset($attrs['name']);

            // Campi da passare via fill (solo quelli in $fillable)
            $fillable = array_diff_key($attrs, ['osmid' => true, 'name' => true, 'properties' => true]);

            if ($existing) {
                $existing->fill($fillable);
                $existing->properties = $this->mergeImportedEcPoiProperties(
                    $existing->properties ?? [],
                    $attrs['properties'],
                );
                $existing->setAttribute('osmid', $dto->osmid);
                $this->applyNameTranslations($existing, $dto, $taxonomy);
                if ($hasGlobalColumn) {
                    $existing->global = $global;
                }
                $existing->save();
                $poi = $existing;
            } else {
                $poi = new EcPoi;
                $poi->fill($fillable);
                $poi->properties = $attrs['properties'];
                $poi->setAttribute('osmid', $dto->osmid);
                if ($userId !== null) {
                    $poi->setAttribute('user_id', $userId);
                }
                if ($hasGlobalColumn) {
                    $poi->global = $global;
                }
                $this->applyNameTranslations($poi, $dto, $taxonomy);
                $poi->save();
            }

            $poi->taxonomyPoiTypes()->syncWithoutDetaching([$taxonomy->id]);

            return $poi;
        });

        return [
            'action' => $action,
            'osmid' => $osmid,
            'ec_poi_id' => $ecPoi->id,
            'taxonomy_identifier' => $taxonomy->identifier,
            'taxonomy_created' => $resolution['created'],
        ];
    }

    /**
     * Record già importato dallo stesso node OSM: prima `properties->osmid` (JSON, utile se la
     * colonna `osmid` non è valorizzata), poi la colonna `ec_pois.osmid`.
     */
    private function findExistingEcPoiByOsmid(int $osmid): ?EcPoi
    {
        $byProperties = EcPoi::query()
            ->where(function ($query) use ($osmid) {
                $query->where('properties->osmid', $osmid)
                    ->orWhere('properties->osmid', (string) $osmid);
            })
            ->first();

        if ($byProperties !== null) {
            return $byProperties;
        }

        return EcPoi::query()->where('osmid', $osmid)->first();
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     *
     * @throws OsmClientException Se l'endpoint OSM non risponde JSON valido o il node non esiste.
     */
    private function fetchOsmNode(int $osmid): array
    {
        try {
            $payload = $this->osmClient->getPropertiesAndGeometry("node/{$osmid}");
        } catch (OsmClientException $e) {
            // Già descritto correttamente dal client (es. OsmClientExceptionNoTags).
            throw $e;
        } catch (\TypeError $e) {
            // OsmClient lancia TypeError quando `Http::get(...)->json()` torna null
            // (typicamente: HTTP 404/410 con body non JSON per OSMID inesistenti).
            throw new OsmClientException('non trovato su OpenStreetMap o risposta non valida.');
        }

        if (! isset($payload[0], $payload[1]) || ! is_array($payload[0]) || ! is_array($payload[1])) {
            throw new OsmClientException('risposta OSM inattesa.');
        }

        return [$payload[0], $payload[1]];
    }

    /**
     * Imposta le traduzioni del nome.
     *
     * Strategia, in ordine:
     *  1. Tag OSM (`name`, `name:it`, `name:en`, ...) raccolti in {@see OsmNodePoiData::$nameTranslations}.
     *  2. Se l'OSM non fornisce alcun `name*`, si usa il nome tradotto del {@see TaxonomyPoiType}
     *     matchato/creato (es. "Punto panoramico" / "Viewpoint"), così il POI eredita un nome
     *     coerente con la categoria invece della label OSM titlecased.
     *  3. Ultimo fallback: {@see OsmNodePoiData::primaryName()} (es. "OSM node 123") per non
     *     salvare un nome vuoto.
     */
    private function applyNameTranslations(EcPoi $poi, OsmNodePoiData $dto, TaxonomyPoiType $taxonomy): void
    {
        if ($dto->nameTranslations !== []) {
            foreach ($dto->nameTranslations as $locale => $value) {
                if ($value === '') {
                    continue;
                }
                $poi->setTranslation('name', $locale, $value);
            }

            return;
        }

        $taxonomyNames = array_filter(
            $taxonomy->getTranslations('name'),
            static fn ($value) => is_string($value) && $value !== '',
        );

        if ($taxonomyNames !== []) {
            foreach ($taxonomyNames as $locale => $value) {
                $poi->setTranslation('name', (string) $locale, $value);
            }

            return;
        }

        $poi->setTranslation('name', 'it', $dto->primaryName());
    }

    /**
     * @param  list<int|string>  $osmIds
     * @return list<int>
     */
    private function normalizeIds(array $osmIds): array
    {
        $ids = [];
        foreach ($osmIds as $value) {
            $trimmed = trim((string) $value);
            if ($trimmed === '' || ! ctype_digit($trimmed)) {
                continue;
            }
            $ids[] = (int) $trimmed;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Unisce le properties esistenti con quelle dell'import OSM e rimuove chiavi legacy
     * che altrimenti resterebbero per effetto di {@see array_merge} (es. `related_url_assoc`
     * prima dell’allineamento al DTO base, oppure il blocco `osm` sostituito da `osm_data` + `osmid`).
     *
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $import
     * @return array<string, mixed>
     */
    private function mergeImportedEcPoiProperties(array $existing, array $import): array
    {
        $merged = array_merge($existing, $import);
        unset($merged['related_url_assoc'], $merged['osm']);

        return $merged;
    }
}
