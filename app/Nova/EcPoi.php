<?php

namespace App\Nova;

use App\Nova\Actions\ImportEcPoiFromOsm;
use Laravel\Nova\Http\Requests\NovaRequest;
use Wm\WmPackage\Nova\EcPoi as WmNovaEcPoi;

class EcPoi extends WmNovaEcPoi
{
    public function actions(NovaRequest $request): array
    {
        return [
            ...parent::actions($request),
            new ImportEcPoiFromOsm,
        ];
    }
}
