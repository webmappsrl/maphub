<?php

declare(strict_types=1);

namespace App\Dto;

use Wm\WmPackage\Dto\EcPoiPropertiesData;

/**
 * DTO project-specific che estende {@see EcPoiPropertiesData} aggiungendo i campi properties
 * effettivamente esposti dalla resource Nova `EcPoi` ma non presenti sul DTO base
 * (`opening_hours`, `addr_locality`, `addr_housenumber`) + dati di provenienza OSM.
 *
 * Struttura allineata alla convenzione wm-package (vedi EcTrackService / HasDemClassification):
 *  - `osmid` al top-level di `properties` (field dedicato, leggibile da query SQL dirette)
 *  - `osm_data` al top-level di `properties`: dizionario con `type`, `source_updated_at`, `tags`
 *
 * Pattern allineato al commento del DTO base: classe readonly, override di `toArray()` che fa
 * `array_merge(parent::toArray(), [...])` per non perdere il filtro dei null.
 *
 * Costruito esclusivamente via {@see self::fromOsmTags()}: il caller passa l'array di tag OSM
 * normalizzati e ottiene una mappa pulita di sole chiavi/valori valorizzati.
 */
readonly class OsmEcPoiPropertiesData extends EcPoiPropertiesData
{
    /**
     * @param  array<string, string>|null  $description
     * @param  array<string, string>|null  $excerpt
     * @param  array<string, string>|null  $related_url  Mappa "label" ⇒ url (es. ['website' => 'https://...']); passata al parent per serializzazione standard.
     * @param  array<string, mixed>|null  $osm_data  Blocco audit allineato alla convenzione wm-package.
     * @param  int|null  $osmid  ID numerico del node OSM, salvato top-level per compatibilità con query SQL.
     */
    public function __construct(
        ?array $description = null,
        ?array $excerpt = null,
        ?string $out_source_feature_id = null,
        ?string $addr_complete = null,
        ?int $capacity = null,
        ?string $contact_phone = null,
        ?string $contact_email = null,
        ?array $related_url = null,
        public ?string $opening_hours = null,
        public ?string $addr_locality = null,
        public ?string $addr_housenumber = null,
        public ?array $osm_data = null,
        public ?int $osmid = null,
    ) {
        parent::__construct(
            description: $description,
            excerpt: $excerpt,
            out_source_feature_id: $out_source_feature_id,
            addr_complete: $addr_complete,
            capacity: $capacity,
            contact_phone: $contact_phone,
            contact_email: $contact_email,
            related_url: $related_url,
        );
    }

    /**
     * Factory: derivata dai tag OSM (già normalizzati a string).
     *
     * @param  array<string, string>  $tags
     * @param  array<string, mixed>  $audit  Deve contenere `osmid` (int), `type` (string), `source_updated_at` (string|null), `tags` (array).
     */
    public static function fromOsmTags(array $tags, array $audit = []): self
    {
        $capacity = self::firstNonEmpty($tags, ['capacity']);
        $capacityInt = $capacity !== null && ctype_digit(trim($capacity)) ? (int) $capacity : null;

        $osmid = isset($audit['osmid']) && is_int($audit['osmid']) ? $audit['osmid'] : null;

        $osmData = $audit !== [] ? array_filter([
            'type' => $audit['type'] ?? null,
            'source_updated_at' => $audit['source_updated_at'] ?? null,
            'tags' => $audit['tags'] ?? null,
        ], static fn ($v) => $v !== null) : null;

        return new self(
            description: self::extractTranslated($tags, 'description'),
            excerpt: self::extractTranslated($tags, 'inscription'),
            out_source_feature_id: isset($tags['ref']) && $tags['ref'] !== '' ? $tags['ref'] : null,
            addr_complete: self::buildCompleteAddress($tags),
            capacity: $capacityInt,
            contact_phone: self::firstNonEmpty($tags, ['contact:phone', 'phone']),
            contact_email: self::firstNonEmpty($tags, ['contact:email', 'email']),
            related_url: self::extractRelatedUrls($tags),
            opening_hours: self::firstNonEmpty($tags, ['opening_hours']),
            addr_locality: self::firstNonEmpty($tags, ['addr:city']),
            addr_housenumber: self::firstNonEmpty($tags, ['addr:housenumber']),
            osm_data: $osmData !== [] ? $osmData : null,
            osmid: $osmid,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $extra = array_filter([
            'osmid' => $this->osmid,
            'opening_hours' => $this->opening_hours,
            'addr_locality' => $this->addr_locality,
            'addr_housenumber' => $this->addr_housenumber,
            'osm_data' => $this->osm_data,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);

        return array_merge(parent::toArray(), $extra);
    }

    /**
     * @param  array<string, string>  $tags
     * @param  list<string>  $keys
     */
    private static function firstNonEmpty(array $tags, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($tags[$key]) && trim($tags[$key]) !== '') {
                return trim($tags[$key]);
            }
        }

        return null;
    }

    /**
     * Combina addr:street, addr:housenumber, addr:postcode, addr:city in un'unica stringa
     * "Via Esempio 12, 41023 Lama Mocogno". Restituisce null se non c'è nulla di significativo.
     *
     * @param  array<string, string>  $tags
     */
    private static function buildCompleteAddress(array $tags): ?string
    {
        $street = self::firstNonEmpty($tags, ['addr:street']);
        $housenumber = self::firstNonEmpty($tags, ['addr:housenumber']);
        $postcode = self::firstNonEmpty($tags, ['addr:postcode']);
        $city = self::firstNonEmpty($tags, ['addr:city']);

        $streetPart = trim(($street ?? '').' '.($housenumber ?? ''));
        $cityPart = trim(($postcode ?? '').' '.($city ?? ''));

        $parts = array_values(array_filter([$streetPart, $cityPart], static fn ($p) => $p !== ''));
        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }

    /**
     * Estrae i link "esterni" come mappa "label" ⇒ url compatibile con Nova KeyValue.
     *
     * @param  array<string, string>  $tags
     * @return array<string, string>|null
     */
    private static function extractRelatedUrls(array $tags): ?array
    {
        $out = [];

        foreach (['website', 'contact:website', 'url'] as $key) {
            if (isset($tags[$key]) && $tags[$key] !== '' && self::isHttpUrl($tags[$key])) {
                $out['website'] = $tags[$key];
                break;
            }
        }

        if (isset($tags['wikipedia']) && $tags['wikipedia'] !== '') {
            $out['wikipedia'] = self::wikipediaUrl($tags['wikipedia']);
        }
        if (isset($tags['wikidata']) && preg_match('/^Q\d+$/', $tags['wikidata']) === 1) {
            $out['wikidata'] = 'https://www.wikidata.org/wiki/'.$tags['wikidata'];
        }

        return $out === [] ? null : $out;
    }

    private static function isHttpUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }

    /**
     * Trasforma "en:Title" oppure URL completi in URL Wikipedia canonico.
     */
    private static function wikipediaUrl(string $value): string
    {
        if (self::isHttpUrl($value)) {
            return $value;
        }
        if (preg_match('/^([a-z]{2,3}):(.+)$/', $value, $m) === 1) {
            return "https://{$m[1]}.wikipedia.org/wiki/".rawurlencode(str_replace(' ', '_', $m[2]));
        }

        return 'https://en.wikipedia.org/wiki/'.rawurlencode(str_replace(' ', '_', $value));
    }

    /**
     * Estrae traduzioni per `description` / `excerpt` con fallback al tag base.
     *
     * @param  array<string, string>  $tags
     * @return array<string, string>|null
     */
    private static function extractTranslated(array $tags, string $base): ?array
    {
        $out = [];

        if (isset($tags["{$base}:it"]) && $tags["{$base}:it"] !== '') {
            $out['it'] = $tags["{$base}:it"];
        }
        if (isset($tags["{$base}:en"]) && $tags["{$base}:en"] !== '') {
            $out['en'] = $tags["{$base}:en"];
        }
        if (! isset($out['it']) && isset($tags[$base]) && $tags[$base] !== '') {
            $out['it'] = $tags[$base];
        }

        return $out === [] ? null : $out;
    }
}
