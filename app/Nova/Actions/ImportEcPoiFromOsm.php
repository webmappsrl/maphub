<?php

declare(strict_types=1);

namespace App\Nova\Actions;

use App\Models\User;
use App\Services\Osm\ImportReport;
use App\Services\Osm\OsmPoiImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Wm\WmPackage\Models\App;

/**
 * Importa POI (`EcPoi`) a partire da OSMID di tipo `node` separati da virgola.
 *
 * UI: textarea + select app (se più di una) + utente obbligatorio (ricercabile) + dry-run.
 * Logica: delegata interamente a {@see OsmPoiImporter}.
 */
class ImportEcPoiFromOsm extends Action
{
    use InteractsWithQueue, Queueable;

    public $standalone = true;

    public $confirmText = 'Verranno scaricati i dati da openstreetmap.org per ogni OSMID. Continuare?';

    public $confirmButtonText = 'Importa';

    public function name(): string
    {
        return __('Import POI da OSM');
    }

    public function handle(ActionFields $fields, Collection $models): mixed
    {
        $rawOsmIds = (string) ($fields->get('osm_ids') ?? '');
        $osmIds = $this->parseOsmIds($rawOsmIds);

        if ($osmIds === []) {
            return Action::danger(__('Nessun OSMID valido trovato. Inserire ID numerici separati da virgola.'));
        }

        $appId = $this->resolveAppId($fields);
        if ($appId === null) {
            return Action::danger(__('Nessuna App selezionata o disponibile.'));
        }

        $userId = $this->resolveUserId($fields);
        $dryRun = (bool) $fields->get('dry_run');

        /** @var OsmPoiImporter $importer */
        $importer = app(OsmPoiImporter::class);
        $report = $importer->importNodes($osmIds, $appId, $userId, $dryRun);

        $message = $this->buildSummaryMessage($report, count($osmIds));

        if ($report->failuresCount() > 0 && ($report->createdCount() + $report->updatedCount()) === 0) {
            return Action::danger($message);
        }

        return Action::message($message);
    }

    /**
     * Costruisce un messaggio riassuntivo con: importati / aggiornati / skippati raggruppati per
     * categoria + i primi OSMID che hanno fallito (max 5), così l'utente capisce subito cosa
     * non è stato importato.
     */
    private function buildSummaryMessage(ImportReport $report, int $requested): string
    {
        $prefix = $report->dryRun ? '[DRY-RUN] ' : '';

        $lines = [];
        $lines[] = $prefix.__('Richiesti :req OSMID. Importati :created, aggiornati :updated, skippati :fail.', [
            'req' => $requested,
            'created' => $report->createdCount(),
            'updated' => $report->updatedCount(),
            'fail' => $report->failuresCount(),
        ]);

        if ($report->newTaxonomiesCount() > 0) {
            $lines[] = __('Nuove taxonomy create: :tax.', ['tax' => $report->newTaxonomiesCount()]);
        }

        $byCategory = $report->failuresByCategory();
        if ($byCategory !== []) {
            $parts = [];
            foreach ($byCategory as $category => $count) {
                $label = ImportReport::CATEGORY_LABELS[$category] ?? $category;
                $parts[] = "{$label}: {$count}";
            }
            $lines[] = __('Motivi degli skip — ').implode(' · ', $parts);
        }

        if ($report->failuresCount() > 0) {
            $firstErrors = array_slice($report->failures(), 0, 5);
            $ids = collect($firstErrors)
                ->map(static fn ($f) => 'node/'.$f['osmid'])
                ->implode(', ');
            $more = $report->failuresCount() - count($firstErrors);
            $suffix = $more > 0 ? " (+{$more} altri)" : '';
            $lines[] = __('OSMID non importati (primi): ').$ids.$suffix;
        }

        return implode(' — ', $lines);
    }

    public function fields(NovaRequest $request): array
    {
        $fields = [
            Textarea::make(__('OSMID dei node (separati da virgola)'), 'osm_ids')
                ->rows(4)
                ->help(__('Esempio: 12345, 67890, 11223. Solo node OSM (i punti).'))
                ->rules('required', 'string', 'max:10000'),
        ];

        $apps = App::query()->orderBy('id')->get();
        if ($apps->count() > 1) {
            $fields[] = Select::make(__('App'), 'app_id')
                ->options($apps->pluck('name', 'id')->toArray())
                ->rules('required')
                ->displayUsingLabels();
        }

        $usersForSelect = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $userOptions = $usersForSelect->mapWithKeys(
            static fn (User $u): array => [$u->id => "{$u->name} ({$u->email})"]
        )->all();

        $fields[] = Select::make(__('Utente'), 'user_id')
            ->options($userOptions)
            ->searchable()
            ->displayUsingLabels()
            ->help(__('Proprietario del POI (obbligatorio).'))
            ->rules('required', 'integer', Rule::exists(User::class, 'id'));

        $fields[] = Boolean::make(__('Dry run (nessuna scrittura)'), 'dry_run')
            ->default(false)
            ->help(__('Se attivo: scarica e mostra l\'esito senza creare/modificare nulla.'));

        return $fields;
    }

    /**
     * @return list<int>
     */
    private function parseOsmIds(string $input): array
    {
        $ids = [];
        foreach (preg_split('/[\s,;]+/', $input) ?: [] as $token) {
            $token = trim($token);
            if ($token === '' || ! ctype_digit($token)) {
                continue;
            }
            $ids[] = (int) $token;
        }

        return array_values(array_unique($ids));
    }

    private function resolveAppId(ActionFields $fields): ?int
    {
        $appIdFromField = $fields->get('app_id');
        if (! empty($appIdFromField)) {
            return (int) $appIdFromField;
        }

        $apps = App::query()->orderBy('id')->limit(2)->get();
        if ($apps->count() === 1) {
            return (int) $apps->first()->id;
        }

        return null;
    }

    private function resolveUserId(ActionFields $fields): int
    {
        return (int) $fields->get('user_id');
    }
}
