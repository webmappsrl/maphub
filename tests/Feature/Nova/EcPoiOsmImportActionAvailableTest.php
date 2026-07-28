<?php

declare(strict_types=1);

use App\Nova\EcPoi;
use Laravel\Nova\Http\Requests\NovaRequest;
use Wm\WmPackage\Nova\Actions\ImportEcPoiFromOsm;

it('exposes the OSM import action on the EcPoi Nova resource by default', function () {
    $request = NovaRequest::create('/');

    $resource = new EcPoi(new \Wm\WmPackage\Models\EcPoi);
    $actions = $resource->actions($request);

    expect(collect($actions)->contains(fn ($action) => $action instanceof ImportEcPoiFromOsm))->toBeTrue();
});
