<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Http\Requests\NovaRequest;
use Wm\WmPackage\Models\User;
use Wm\WmPackage\Nova\App as WmNovaApp;
use Wm\WmPackage\Support\SuperAdminService;

class App extends WmNovaApp
{
    public static function relatableUsers(NovaRequest $request, $query)
    {
        return $query->role('Administrator');
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return SuperAdminService::allows($request);
    }

    public static function createButtonLabel(): string
    {
        return __('Create App');
    }
}
