<?php

namespace App\Nova;

use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Http\Requests\NovaRequest;
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
}
