<?php

declare(strict_types=1);

namespace App\Nova\Actions;

use App\Services\Osm\OsmImportReportPresenter;
use App\Services\Osm\OsmImportReportStore;
use App\Services\Osm\OsmPoiImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\User;

/**
 * Importa POI (`EcPoi`) a partire da OSMID di tipo `node` separati da virgola.
 *
 * Testi UI: chiavi inglesi con {@see __()} e traduzioni in `lang/{it,en,fr,es,de}.json`.
 *
 * UI: textarea + select app + global + dry-run. L'utente proprietario dei POI non viene scelto
 * dall'operatore ma derivato da {@see App::$user_id} dell'app selezionata.
 *
 * Al termine (anche dry-run) la risposta è un redirect alla pagina di report interna (stessa scheda),
 * senza toast né popup esterni.
 *
 * Visibilità app:
 *  - utente con email {@see self::SUPER_USER_EMAIL} → tutte le app
 *  - altri utenti → solo le app di cui sono `user_id` (relazione {@see User::apps()})
 *
 * Select App: prima app (per nome) pre-selezionata; se l’utente ne vede una sola, la select è in sola lettura.
 */
class ImportEcPoiFromOsm extends Action
{
    use InteractsWithQueue, Queueable;

    /** Email autorizzata a vedere tutte le app nella select (vedi @AbstractAuthorableObserver). */
    private const SUPER_USER_EMAIL = 'team@webmapp.it';

    public $standalone = true;

    public function __construct()
    {
        // Stringhe inglesi: Nova applica `Nova::__()` in serializzazione (locale utente corretto).
        $this->confirmText = 'Data will be downloaded from openstreetmap.org for each OSM ID. Continue?';
        $this->confirmButtonText = 'Import';
    }

    public function name(): string
    {
        return __('Import POIs from OSM');
    }

    public function handle(ActionFields $fields, Collection $models): mixed
    {
        $rawOsmIds = (string) ($fields->get('osm_ids') ?? '');
        $osmIds = $this->parseOsmIds($rawOsmIds);

        if ($osmIds === []) {
            return Action::danger(__('No valid OSM IDs found. Enter numeric IDs separated by commas.'));
        }

        $app = $this->resolveApp($fields);
        if ($app === null) {
            return Action::danger(__('No app selected or available.'));
        }

        $userId = $app->user_id !== null ? (int) $app->user_id : null;
        $dryRun = (bool) $fields->get('dry_run');
        $global = (bool) $fields->get('global', true);

        /** @var OsmPoiImporter $importer */
        $importer = app(OsmPoiImporter::class);
        $report = $importer->importNodes($osmIds, (int) $app->id, $userId, $dryRun, $global);

        $payload = OsmImportReportPresenter::payload($report, count($osmIds));
        $token = OsmImportReportStore::put($payload, (int) Auth::id());

        return Action::redirect(route('osm.import.report', ['token' => $token]));
    }

    public function fields(NovaRequest $request): array
    {
        $fields = [
            Textarea::make(__('OSM node IDs (comma-separated)'), 'osm_ids')
                ->rows(4)
                ->help(__('Example: 12345, 67890, 11223. OSM nodes only (points).') . ' ' . __('If an OSM ID was already imported, its POI will be updated.'))
                ->rules('required', 'string', 'max:10000'),
        ];

        $apps = $this->visibleAppsFor($request->user())->orderBy('name')->get();

        $appSelect = Select::make(__('App'), 'app_id')
            ->options($apps->pluck('name', 'id')->toArray())
            ->rules('required')
            ->searchable()
            ->displayUsingLabels()
            ->help(__('The POI owner is automatically set to the user_id of the selected app.'));

        if ($apps->isNotEmpty()) {
            $appSelect->default($apps->first()->id);
        }

        if ($apps->count() === 1) {
            $appSelect->readonly();
        }

        $fields[] = $appSelect;

        $fields[] = Boolean::make(__('Include in app pois.geojson (EcPoi.global = true)'), 'global')
            ->default(true)
            ->help(__('When enabled, POIs are included in the app’s pois.geojson file (global filter in getAllPoisGeojson). When disabled, they are imported but excluded from that file until global is set to true.'));

        $fields[] = Boolean::make(__('Dry run (no writes)'), 'dry_run')
            ->default(false)
            ->help(__('When enabled, data is fetched and the outcome is shown without persisting any changes.'));

        return $fields;
    }

    /**
     * Query delle app visibili all'utente corrente:
     *  - `team@webmapp.it` (e Administrator) → tutte le app;
     *  - altri utenti → solo quelle di cui sono proprietari (`apps.user_id = user.id`).
     */
    private function visibleAppsFor(?User $user): Builder
    {
        $query = App::query();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->email === self::SUPER_USER_EMAIL || $user->hasRole('Administrator')) {
            return $query;
        }

        return $query->where('user_id', $user->id);
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

    /**
     * Risolve l'app: dal campo `app_id` selezionato, oppure auto-selezione se l'utente vede una sola app.
     * Garantisce inoltre che l'app sia tra quelle visibili (no bypass via form tampering).
     */
    private function resolveApp(ActionFields $fields): ?App
    {
        $user = Auth::user();
        $visible = $this->visibleAppsFor($user instanceof User ? $user : null);

        $appIdFromField = $fields->get('app_id');
        if (! empty($appIdFromField)) {
            return $visible->where('id', (int) $appIdFromField)->first();
        }

        $apps = (clone $visible)->limit(2)->get();

        return $apps->count() === 1 ? $apps->first() : null;
    }
}
