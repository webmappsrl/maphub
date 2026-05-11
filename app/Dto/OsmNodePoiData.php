<?php

declare(strict_types=1);

namespace App\Dto;

use App\Models\EcPoi;
use Wm\WmPackage\Dto\EcPoiPropertiesData;
use Wm\WmPackage\Http\Clients\OsmClient;

/**
 * Data Transfer Object: rappresentazione normalizzata di un node OSM
 * pronta a essere mappata su un {@see EcPoi} (estende EcPoi del wm-package).
 *
 * Pattern allineato a {@see EcPoiPropertiesData}: classe readonly,
 * immutabile, con factory `fromOsmJson()` che incapsula il parsing del JSON v0.6.
 *
 * I tag OSM presi in considerazione per derivare la coppia
 * (chiave-OSM, valore-OSM) usata come "poi type" sono in {@see self::POI_TYPE_TAG_KEYS},
 * in ordine alfabetico: la prima chiave presente sui tag del node vince.
 */
readonly class OsmNodePoiData
{
    /**
     * Chiavi tag OSM accettate per derivare il TaxonomyPoiType.
     * Ordine alfabetico: la prima chiave presente (e non vuota / non "no") vince.
     *
     * @var list<string>
     */
    public const POI_TYPE_TAG_KEYS = [
        'advertising',
        'aerialway',
        'aeroway',
        'amenity',
        'attraction',
        'barrier',
        'boundary',
        'building',
        'checkpoint',
        'club',
        'craft',
        'emergency',
        'entrance',
        'ford',
        'geological',
        'healthcare',
        'highway',
        'historic',
        'landuse',
        'leisure',
        'man_made',
        'military',
        'mountain_pass',
        'natural',
        'office',
        'place',
        'power',
        'public_transport',
        'railway',
        'route',
        'shop',
        'sport',
        'telecom',
        'tourism',
        'traffic_calming',
        'traffic_sign',
        'water',
        'waterway',
    ];

    /**
     * @param  int  $osmid  Id numerico del node OSM (senza prefisso "node/").
     * @param  array<string, string>  $nameTranslations  Mappa locale ⇒ name (es. ['it' => 'Belvedere', 'en' => 'Viewpoint']).
     * @param  float  $lat  Latitudine WGS84.
     * @param  float  $lng  Longitudine WGS84.
     * @param  string|null  $poiTypeOsmKey  Chiave OSM scelta come classificante (es. "tourism"), null se non classificato.
     * @param  string|null  $poiTypeOsmValue  Valore OSM scelto (es. "viewpoint"), null se non classificato.
     * @param  array<string, string>  $rawTags  Tags OSM completi (per audit/debug nelle properties).
     * @param  string|null  $sourceUpdatedAt  Timestamp "_updated_at" calcolato da OsmClient.
     */
    public function __construct(
        public int $osmid,
        public array $nameTranslations,
        public float $lat,
        public float $lng,
        public ?string $poiTypeOsmKey,
        public ?string $poiTypeOsmValue,
        public array $rawTags = [],
        public ?string $sourceUpdatedAt = null,
    ) {}

    /**
     * Factory: costruisce il DTO a partire dalla risposta di {@see OsmClient::getPropertiesAndGeometry()}
     * per un node OSM. La firma è strettamente quella di un node (geometry "Point"); accettare way/relation
     * richiederebbe una strategia di centroide separata e non è scopo di questo importer.
     *
     * @param  array<string, mixed>  $properties  Tags + chiavi tecniche ("_updated_at", ...) restituite da OsmClient.
     * @param  array<string, mixed>  $geometry  Geometry GeoJSON Point (validata difensivamente).
     *
     * @throws \InvalidArgumentException Quando la geometry non è un Point valido.
     */
    public static function fromOsmNode(int $osmid, array $properties, array $geometry): self
    {
        $type = $geometry['type'] ?? null;
        $coordinates = $geometry['coordinates'] ?? null;
        if ($type !== 'Point' || ! is_array($coordinates) || ! isset($coordinates[0], $coordinates[1])) {
            throw new \InvalidArgumentException("OSM node {$osmid}: geometry non è un Point valido.");
        }

        $lng = (float) $coordinates[0];
        $lat = (float) $coordinates[1];

        $tags = [];
        foreach ($properties as $key => $value) {
            if (str_starts_with($key, '_')) {
                continue;
            }
            $tags[$key] = is_scalar($value) ? (string) $value : '';
        }

        [$poiKey, $poiValue] = self::pickPoiTypeFromTags($tags);

        return new self(
            osmid: $osmid,
            nameTranslations: self::extractNameTranslations($tags),
            lat: $lat,
            lng: $lng,
            poiTypeOsmKey: $poiKey,
            poiTypeOsmValue: $poiValue,
            rawTags: $tags,
            sourceUpdatedAt: isset($properties['_updated_at']) && is_string($properties['_updated_at'])
                ? $properties['_updated_at']
                : null,
        );
    }

    /**
     * Nome principale (default = italiano, fallback al primo disponibile, infine all'OSMID).
     */
    public function primaryName(): string
    {
        if (isset($this->nameTranslations['it']) && $this->nameTranslations['it'] !== '') {
            return $this->nameTranslations['it'];
        }
        $firstKey = array_key_first($this->nameTranslations);

        return $firstKey !== null ? $this->nameTranslations[$firstKey] : "OSM node {$this->osmid}";
    }

    /**
     * Identifier "umano" candidato per il TaxonomyPoiType, costruito come "chiave-valore"
     * (es. "tourism-viewpoint"). Restituisce null se il node non ha un tag classificante.
     */
    public function poiTypeCompositeIdentifier(): ?string
    {
        if ($this->poiTypeOsmKey === null || $this->poiTypeOsmValue === null) {
            return null;
        }

        return self::normalizeIdentifier("{$this->poiTypeOsmKey}-{$this->poiTypeOsmValue}");
    }

    /**
     * Normalizza un identifier per il confronto/persistenza: lowercase, trim,
     * spazi/underscore in trattini, caratteri non alfanumerici (eccetto "-") rimossi.
     */
    public static function normalizeIdentifier(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = preg_replace('/[\s_]+/', '-', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9\-]/', '', $value) ?? $value;

        return trim($value, '-');
    }

    /**
     * Restituisce gli attributi pronti per {@see \Wm\WmPackage\Models\EcPoi::create()}.
     * Il name resta translatable: viene impostato via setTranslation lato caller.
     *
     * Il blocco `properties` è prodotto dal DTO {@see OsmEcPoiPropertiesData} a partire dai tag OSM:
     * mappa le chiavi standard (contact_email, contact_phone, opening_hours, addr_*, related_url, ...)
     * e conserva i tag grezzi sotto `properties.osm.tags` per audit/debug.
     *
     * @return array<string, mixed>
     */
    public function toEcPoiAttributes(int $appId, ?int $userId = null): array
    {
        $properties = $this->toEcPoiProperties()->toArray();

        $attrs = [
            'osmid' => $this->osmid,
            'app_id' => $appId,
            'geometry' => sprintf('POINT(%F %F)', $this->lng, $this->lat),
            'name' => $this->primaryName(),
            'properties' => $properties,
        ];

        if ($userId !== null) {
            $attrs['user_id'] = $userId;
        }

        return $attrs;
    }

    /**
     * Costruisce il DTO delle properties usando i tag OSM.
     * La struttura risultante è allineata alla convenzione wm-package:
     *  - `properties.osmid` → ID numerico top-level (compatibile con query SQL / EcTrackService)
     *  - `properties.osm_data` → blocco audit con `type`, `source_updated_at`, `tags`
     */
    public function toEcPoiProperties(): OsmEcPoiPropertiesData
    {
        return OsmEcPoiPropertiesData::fromOsmTags(
            $this->rawTags,
            [
                'osmid' => $this->osmid,
                'type' => 'node',
                'source_updated_at' => $this->sourceUpdatedAt,
                'tags' => $this->rawTags,
            ],
        );
    }

    /**
     * Sceglie la coppia (chiave, valore) OSM da usare come classificante.
     *
     * @param  array<string, string>  $tags
     * @return array{0: ?string, 1: ?string}
     */
    private static function pickPoiTypeFromTags(array $tags): array
    {
        foreach (self::POI_TYPE_TAG_KEYS as $key) {
            if (isset($tags[$key]) && $tags[$key] !== '' && $tags[$key] !== 'no') {
                return [$key, $tags[$key]];
            }
        }

        return [null, null];
    }

    /**
     * Estrae le traduzioni del name dai tag OSM (`name`, `name:it`, `name:en`, ...).
     * Se OSM non fornisce alcun `name*`, ritorna array vuoto: il fallback (es. nome della
     * tassonomia matchata) è responsabilità del chiamante.
     *
     * @param  array<string, string>  $tags
     * @return array<string, string>
     */
    private static function extractNameTranslations(array $tags): array
    {
        $translations = [];

        if (isset($tags['name:it']) && $tags['name:it'] !== '') {
            $translations['it'] = $tags['name:it'];
        }
        if (isset($tags['name:en']) && $tags['name:en'] !== '') {
            $translations['en'] = $tags['name:en'];
        }

        if (! isset($translations['it']) && isset($tags['name']) && $tags['name'] !== '') {
            $translations['it'] = $tags['name'];
        }

        return $translations;
    }
}
