<?php

namespace App\Providers;

use App\Models\User;
use App\Nova\App as NovaApp;
use App\Nova\Dashboards\Main;
use App\Nova\EcPoi;
use App\Nova\EcTrack;
use App\Nova\Layer;
use App\Nova\Media as NovaMedia;
use App\Nova\TaxonomyActivity;
use App\Nova\TaxonomyPoiType;
use App\Nova\TaxonomyTheme;
use App\Nova\TaxonomyWhere;
use App\Nova\Tile;
use App\Nova\UgcMedia;
use App\Nova\UgcPoi;
use App\Nova\UgcTrack;
use App\Nova\User as NovaUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Laravel\Fortify\Features;
use Laravel\Nova\Dashboard;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;
use Laravel\Nova\Tool;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        $this->getFooter();

        Nova::mainMenu(function (Request $request) {
            return [
                MenuSection::dashboard(Main::class)->icon('chart-bar'),

                MenuSection::make(__('Admin'), [
                    MenuItem::resource(NovaApp::class)
                        ->canSee(fn (Request $request) => $request->user()->hasRole('Administrator')),
                    MenuItem::resource(Tile::class)
                        ->canSee(fn (Request $request) => $request->user()->hasRole('Administrator')),
                    MenuItem::resource(NovaUser::class)
                        ->canSee(fn (Request $request) => $request->user()->hasRole('Administrator')),
                    MenuItem::resource(NovaMedia::class)
                        ->canSee(fn (Request $request) => $request->user()->hasRole('Administrator')),
                ])->icon('user')
                    ->canSee(fn (Request $request) => $request->user()->hasRole('Administrator'))
                    ->collapsable()
                    ->collapsedByDefault(),

                MenuSection::make('UGC', [
                    MenuItem::resource(UgcPoi::class),
                    MenuItem::resource(UgcTrack::class),
                    MenuItem::resource(UgcMedia::class),
                ])->icon('document'),

                MenuSection::make('EC', [
                    MenuItem::resource(EcPoi::class),
                    MenuItem::resource(EcTrack::class),
                    MenuItem::resource(Layer::class),
                ])->icon('document'),

                MenuSection::make(__('Taxonomies'), [
                    MenuItem::resource(TaxonomyPoiType::class),
                    MenuItem::resource(TaxonomyActivity::class),
                    MenuItem::resource(TaxonomyTheme::class),
                    MenuItem::resource(TaxonomyWhere::class),
                ])->icon('document'),

                MenuSection::make(__('Files'), [
                    MenuItem::externalLink(__('Icons'), route('icons.upload.show'))->openInNewTab(),
                ])->icon('folder')
                    ->canSee(fn (Request $request) => $request->user()->hasRole('Administrator'))
                    ->collapsable()
                    ->collapsedByDefault(),
            ];
        });
    }

    /**
     * Register the configurations for Laravel Fortify.
     */
    protected function fortify(): void
    {
        Nova::fortify()
            ->features([
                Features::updatePasswords(),
                // Features::emailVerification(),
                // Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
            ])
            ->register();
    }

    /**
     * Register the Nova routes.
     */
    protected function routes(): void
    {
        Nova::routes()
            ->withAuthenticationRoutes(default: true)
            ->withPasswordResetRoutes()
            ->withoutEmailVerificationRoutes()
            ->register();
    }

    /**
     * Register the Nova gate.
     *
     * This gate determines who can access Nova in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewNova', function (User $user) {
            return $user->can('access-nova');
        });
    }

    /**
     * Get the dashboards that should be listed in the Nova sidebar.
     *
     * @return array<int, Dashboard>
     */
    protected function dashboards(): array
    {
        return [
            new Main,
        ];
    }

    /**
     * Get the tools that should be listed in the Nova sidebar.
     *
     * @return array<int, Tool>
     */
    public function tools(): array
    {
        return [];
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        parent::register();

        //
    }

    private function getFooter()
    {
        Nova::footer(function () {
            return Blade::render('nova/footer');
        });
    }
}
