<?php

declare(strict_types=1);

namespace App\Services\Osm;

/**
 * Aggregato di esiti di un'esecuzione di {@see OsmPoiImporter::importNodes()}.
 *
 * Non è un readonly DTO perché viene popolato incrementalmente durante l'iterazione;
 * lo stato resta confinato all'importer (mutazioni solo via i metodi pubblici).
 */
class ImportReport
{
    /** @var list<array{action: string, osmid: int, ec_poi_id: ?int, taxonomy_identifier: ?string, taxonomy_created: bool}> */
    private array $outcomes = [];

    /** @var list<array{osmid: int, error: string, category: string}> */
    private array $failures = [];

    /**
     * Etichette delle categorie di errore, in italiano, ordinate dalla più alla meno comune.
     *
     * @var array<string, string>
     */
    public const CATEGORY_LABELS = [
        'not_found_or_invalid_osm' => 'Node non trovato o risposta OSM non valida',
        'no_tags' => 'Node OSM senza tag (nessun dato utile)',
        'geometry' => 'Geometria OSM non valida',
        'storage' => 'Storage S3/MinIO (disk wmfe) non configurato o errore dopo il salvataggio',
        'other' => 'Altro errore',
    ];

    public function __construct(public readonly bool $dryRun) {}

    /**
     * @param  array{action: string, osmid: int, ec_poi_id: ?int, taxonomy_identifier: ?string, taxonomy_created: bool}  $outcome
     */
    public function addOutcome(array $outcome): void
    {
        $this->outcomes[] = $outcome;
    }

    public function addFailure(int $osmid, string $error, string $category = 'other'): void
    {
        $this->failures[] = [
            'osmid' => $osmid,
            'error' => $error,
            'category' => $category,
        ];
    }

    /** @return list<array{action: string, osmid: int, ec_poi_id: ?int, taxonomy_identifier: ?string, taxonomy_created: bool}> */
    public function outcomes(): array
    {
        return $this->outcomes;
    }

    /** @return list<array{osmid: int, error: string, category: string}> */
    public function failures(): array
    {
        return $this->failures;
    }

    /**
     * Conteggi raggruppati per categoria (es. ['no_tags' => 12, 'not_found_or_invalid_osm' => 9]).
     *
     * @return array<string, int>
     */
    public function failuresByCategory(): array
    {
        $out = [];
        foreach ($this->failures as $f) {
            $out[$f['category']] = ($out[$f['category']] ?? 0) + 1;
        }

        return $out;
    }

    public function createdCount(): int
    {
        return count(array_filter($this->outcomes, static fn ($o) => $o['action'] === 'created'));
    }

    public function updatedCount(): int
    {
        return count(array_filter($this->outcomes, static fn ($o) => $o['action'] === 'updated'));
    }

    public function failuresCount(): int
    {
        return count($this->failures);
    }

    public function newTaxonomiesCount(): int
    {
        return count(array_filter($this->outcomes, static fn ($o) => $o['taxonomy_created'] === true));
    }
}
