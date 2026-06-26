<?php

namespace App\Nova;

use Laravel\Nova\Http\Requests\NovaRequest;
use Vyuldashev\NovaPermission\PermissionBooleanGroup;
use Vyuldashev\NovaPermission\RoleBooleanGroup;
use Wm\WmPackage\Nova\AbstractUserResource;

class User extends AbstractUserResource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\User>
     */
    public static $model = \App\Models\User::class;

    public function fields(NovaRequest $request): array
    {
        return collect(parent::fields($request))
            ->map(fn ($field) => match (true) {
                $field instanceof RoleBooleanGroup,
                $field instanceof PermissionBooleanGroup => $field->hideFromIndex(),
                default => $field,
            })
            ->all();
    }
}
