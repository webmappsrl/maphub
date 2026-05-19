<?php

namespace App\Nova;

use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Nova\Actions\BuildAppPoisGeojsonAction;
use Wm\WmPackage\Nova\App as WmNovaApp;

class App extends WmNovaApp
{
    public function fields(NovaRequest $request): array
    {
        $fields = parent::fields($request);

        $userField = BelongsTo::make(__('User'), 'author', User::class)
            ->nullable()
            ->sortable()
            ->searchable()
            ->hideFromIndex();

        array_splice($fields, 1, 0, [$userField]);

        return $fields;
    }

    public function actions(NovaRequest $request): array
    {
        return array_merge(parent::actions($request), [
            (new BuildAppPoisGeojsonAction)
                ->onlyOnDetail()
                ->canSee(fn (NovaRequest $request) => optional($request->user())->email === 'team@webmapp.it')
                ->canRun(fn (NovaRequest $request) => optional($request->user())->email === 'team@webmapp.it')
                ->confirmText(__('Are you sure you want to regenerate pois.geojson for this app? The job will be queued and may take a while.'))
                ->confirmButtonText(__('Yes, regenerate'))
                ->cancelButtonText(__('Cancel')),
        ]);
    }
}
