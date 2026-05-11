<?php

declare(strict_types=1);

namespace App\Services\Osm;

use App\Dto\OsmNodePoiData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Wm\WmPackage\Models\TaxonomyPoiType;

/**
 * Risolve un {@see TaxonomyPoiType} a partire dalla coppia (chiave-OSM, valore-OSM) ricavata da {@see OsmNodePoiData}.
 *
 * Strategia (in ordine):
 *  1. Match esatto per `identifier` sul valore OSM normalizzato (es. "viewpoint").
 *  2. Match esatto sull'identifier composito "chiave-valore" (es. "tourism-viewpoint").
 *  3. Match case-insensitive con trim (covering legacy identifier salvati con casing diverso).
 *  4. Creazione di un nuovo TaxonomyPoiType con identifier "chiave-valore" e name {it,en} titlecased.
 *
 * Tutte le query usano `identifier` indicizzato/unique (vedi migration `create_taxonomy_poi_types_table`).
 */
class OsmTaxonomyPoiTypeResolver
{
    /**
     * Memoizzazione per evitare query ripetute durante un singolo batch di import.
     *
     * @var array<string, TaxonomyPoiType|null>
     */
    private array $cache = [];

    /**
     * Restituisce un TaxonomyPoiType esistente o ne crea uno nuovo.
     * In dry-run, ritorna l'istanza esistente oppure un'istanza non persistita per consentire al chiamante
     * di sapere quale identifier verrebbe creato.
     *
     * @return array{taxonomy: TaxonomyPoiType, created: bool}
     */
    public function resolve(OsmNodePoiData $data, bool $dryRun = false): array
    {
        if ($data->poiTypeOsmKey === null || $data->poiTypeOsmValue === null) {
            return [
                'taxonomy' => $this->fallbackGenericPoi($dryRun),
                'created' => false,
            ];
        }

        $valueIdentifier = OsmNodePoiData::normalizeIdentifier($data->poiTypeOsmValue);
        $compositeIdentifier = $data->poiTypeCompositeIdentifier() ?? $valueIdentifier;

        if ($existing = $this->findByIdentifier($valueIdentifier)) {
            return ['taxonomy' => $existing, 'created' => false];
        }

        if ($existing = $this->findByIdentifier($compositeIdentifier)) {
            return ['taxonomy' => $existing, 'created' => false];
        }

        if ($dryRun) {
            return [
                'taxonomy' => $this->buildUnsaved($compositeIdentifier, $data->poiTypeOsmValue, $data->nameTranslations),
                'created' => true,
            ];
        }

        return [
            'taxonomy' => $this->createTaxonomy($compositeIdentifier, $data->poiTypeOsmValue, $data->nameTranslations),
            'created' => true,
        ];
    }

    /**
     * Cerca per identifier supportando legacy: prima match esatto, poi case-insensitive ignorando spazi/underscore.
     */
    private function findByIdentifier(string $identifier): ?TaxonomyPoiType
    {
        if ($identifier === '') {
            return null;
        }
        if (array_key_exists($identifier, $this->cache)) {
            return $this->cache[$identifier];
        }

        $match = TaxonomyPoiType::query()
            ->whereRaw('LOWER(BTRIM(identifier)) = ?', [$identifier])
            ->orderBy('id')
            ->first();

        return $this->cache[$identifier] = $match;
    }

    private function createTaxonomy(string $identifier, string $osmValue, array $nameTranslations): TaxonomyPoiType
    {
        $taxonomy = new TaxonomyPoiType;
        $this->applyNewTaxonomyAttributes($taxonomy, $identifier, $osmValue, $nameTranslations);
        $taxonomy->save();

        $this->cache[$identifier] = $taxonomy;

        return $taxonomy;
    }

    private function buildUnsaved(string $identifier, string $osmValue, array $nameTranslations): TaxonomyPoiType
    {
        $taxonomy = new TaxonomyPoiType;
        $this->applyNewTaxonomyAttributes($taxonomy, $identifier, $osmValue, $nameTranslations);

        return $taxonomy;
    }

    /**
     * @param  array<string, string>  $nameTranslations
     */
    private function applyNewTaxonomyAttributes(
        TaxonomyPoiType $taxonomy,
        string $identifier,
        string $osmValue,
        array $nameTranslations,
    ): void {
        $humanName = Str::title(str_replace(['_', '-'], ' ', $osmValue));

        $taxonomy->identifier = $identifier;

        $taxonomy->setTranslation('name', 'it', $nameTranslations['it'] ?? $humanName);
        if (isset($nameTranslations['en']) && $nameTranslations['en'] !== '') {
            $taxonomy->setTranslation('name', 'en', $nameTranslations['en']);
        } else {
            $taxonomy->setTranslation('name', 'en', $humanName);
        }
    }

    /**
     * Quando il node OSM non ha un tag classificante, ripieghiamo su un TaxonomyPoiType "poi" già presente in DB,
     * oppure su un'istanza non persistita (caso dry-run o DB vuoto: non vogliamo creare un fallback "poi" in modo silenzioso).
     */
    private function fallbackGenericPoi(bool $dryRun): TaxonomyPoiType
    {
        if ($existing = $this->findByIdentifier('poi')) {
            return $existing;
        }

        $taxonomy = new TaxonomyPoiType;
        $taxonomy->identifier = 'poi';
        $taxonomy->setTranslation('name', 'it', 'POI');
        $taxonomy->setTranslation('name', 'en', 'POI');

        if (! $dryRun) {
            try {
                DB::transaction(function () use ($taxonomy) {
                    $taxonomy->save();
                });
                $this->cache['poi'] = $taxonomy;
            } catch (\Throwable) {
                // Race condition: ricarica.
                return TaxonomyPoiType::query()->where('identifier', 'poi')->firstOrFail();
            }
        }

        return $taxonomy;
    }
}
