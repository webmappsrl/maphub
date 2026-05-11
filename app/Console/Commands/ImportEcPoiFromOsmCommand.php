<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Osm\ImportReport;
use App\Services\Osm\OsmPoiImporter;
use Illuminate\Console\Command;
use Wm\WmPackage\Models\App;

/**
 * Importa POI a partire da OSMID di tipo node, lato CLI.
 *
 * Esempi:
 *   php artisan maphub:import-ec-pois-from-osm "12345,67890,11223" --app=1
 *   php artisan maphub:import-ec-pois-from-osm 12345 --app=1 --dry-run
 *   php artisan maphub:import-ec-pois-from-osm @osmids.txt --app=1
 *   php artisan maphub:import-ec-pois-from-osm 12345 --app=2 --no-global
 */
class ImportEcPoiFromOsmCommand extends Command
{
    protected $signature = 'maphub:import-ec-pois-from-osm
        {osmids : OSMID di node separati da virgola, oppure "@/path/file.txt" per leggere da file}
        {--app= : ID dell\'App di destinazione (auto se esiste una sola App)}
        {--user= : ID utente proprietario (default: nessuno)}
        {--dry-run : Esegue senza persistere; mostra solo cosa farebbe}
        {--no-global : Imposta EcPoi.global=false (esclusi dal pois.geojson; default: global true)}';

    protected $description = 'Importa nuovi EcPoi da OpenStreetMap (solo node) mappando i tag OSM su TaxonomyPoiType.';

    public function handle(OsmPoiImporter $importer): int
    {
        $osmIds = $this->readOsmIds((string) $this->argument('osmids'));
        if ($osmIds === []) {
            $this->error('Nessun OSMID valido. Inserire ID numerici separati da virgola.');

            return self::INVALID;
        }

        $appId = $this->resolveAppId();
        if ($appId === null) {
            $this->error('Specificare --app=ID; sono presenti più App nel database.');

            return self::INVALID;
        }

        $userId = $this->option('user') !== null ? (int) $this->option('user') : null;
        $dryRun = (bool) $this->option('dry-run');
        $global = ! (bool) $this->option('no-global');

        $this->info(sprintf(
            '%sImporto %d OSMID nell\'App %d%s (global=%s) ...',
            $dryRun ? '[DRY-RUN] ' : '',
            count($osmIds),
            $appId,
            $userId !== null ? " (user_id={$userId})" : '',
            $global ? 'true' : 'false',
        ));

        $report = $importer->importNodes($osmIds, $appId, $userId, $dryRun, $global);

        $this->renderReport($report);

        // SUCCESS se almeno un POI è stato (importato|aggiornato). Skip sono dati di input
        // "non importabili", non errori dell'operazione → non blocchiamo l'exit code.
        $imported = $report->createdCount() + $report->updatedCount();

        return $imported > 0 || $report->failuresCount() === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<int>
     */
    private function readOsmIds(string $raw): array
    {
        if (str_starts_with($raw, '@')) {
            $path = substr($raw, 1);
            if (! is_readable($path)) {
                $this->error("File non leggibile: {$path}");

                return [];
            }
            $raw = (string) file_get_contents($path);
        }

        $ids = [];
        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $token) {
            $token = trim($token);
            if ($token === '' || ! ctype_digit($token)) {
                continue;
            }
            $ids[] = (int) $token;
        }

        return array_values(array_unique($ids));
    }

    private function resolveAppId(): ?int
    {
        $option = $this->option('app');
        if ($option !== null && $option !== '') {
            return (int) $option;
        }

        $apps = App::query()->orderBy('id')->limit(2)->get();
        if ($apps->count() === 1) {
            return (int) $apps->first()->id;
        }

        return null;
    }

    private function renderReport(ImportReport $report): void
    {
        $rows = array_map(
            static fn ($o) => [
                $o['osmid'],
                $o['action'],
                $o['ec_poi_id'] ?? '-',
                $o['taxonomy_identifier'] ?? '-',
                $o['taxonomy_created'] ? 'yes' : 'no',
            ],
            $report->outcomes(),
        );

        if ($rows !== []) {
            $this->table(['OSMID', 'Action', 'EcPoi ID', 'Taxonomy', 'New Taxonomy?'], $rows);
        }

        if ($report->failuresCount() > 0) {
            $this->warn("Skippati ({$report->failuresCount()}):");
            foreach ($report->failures() as $failure) {
                $label = ImportReport::CATEGORY_LABELS[$failure['category']] ?? $failure['category'];
                $this->warn(" - node/{$failure['osmid']} [{$label}]: {$failure['error']}");
            }

            $byCategory = $report->failuresByCategory();
            if ($byCategory !== []) {
                $this->line('Riepilogo skip per motivo:');
                foreach ($byCategory as $category => $count) {
                    $label = ImportReport::CATEGORY_LABELS[$category] ?? $category;
                    $this->line(" - {$label}: {$count}");
                }
            }
        }

        $this->line(sprintf(
            '%sCreati: %d | Aggiornati: %d | Nuove taxonomy: %d | Skippati: %d',
            $report->dryRun ? '[DRY-RUN] ' : '',
            $report->createdCount(),
            $report->updatedCount(),
            $report->newTaxonomiesCount(),
            $report->failuresCount(),
        ));
    }
}
