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
 *
 * L\'utente proprietario dei POI è {@see App::$user_id} dell\'app (come la Nova Action).
 */
class ImportEcPoiFromOsmCommand extends Command
{
    protected $signature = 'maphub:import-ec-pois-from-osm
        {osmids : OSMID di node separati da virgola, oppure "@/path/file.txt" per leggere da file}
        {--app= : ID dell\'App di destinazione (auto se esiste una sola App)}
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

        $appOption = $this->option('app');
        $app = $this->resolveApp();
        if ($app === null) {
            if ($appOption !== null && $appOption !== '') {
                $this->error("Nessuna App con ID {$appOption}.");

                return self::INVALID;
            }
            $this->error('Specificare --app=ID; sono presenti più App nel database.');

            return self::INVALID;
        }

        $appId = (int) $app->id;
        $userId = $app->user_id !== null ? (int) $app->user_id : null;
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

    private function resolveApp(): ?App
    {
        $option = $this->option('app');
        if ($option !== null && $option !== '') {
            return App::query()->find((int) $option);
        }

        $apps = App::query()->orderBy('id')->limit(2)->get();
        if ($apps->count() === 1) {
            return $apps->first();
        }

        return null;
    }

    private function renderReport(ImportReport $report): void
    {
        $rows = array_map(
            static fn($o) => [
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

        if ($report->truncatedBeyondLimit() > 0) {
            $this->warn(sprintf(
                'Attenzione: %d OSMID non processati (limite OSM_IMPORT_MAX_IDS_PER_RUN). Esegui un altro import con gli ID rimanenti.',
                $report->truncatedBeyondLimit(),
            ));
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
