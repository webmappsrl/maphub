<?php

declare(strict_types=1);

use App\Models\User;
use App\Nova\User as NovaUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Laravel\Nova\Http\Requests\NovaRequest;
use Vyuldashev\NovaPermission\RoleBooleanGroup;
use Wm\WmPackage\Nova\AbstractUserResource;
use Wm\WmPackage\Services\RolesAndPermissionsService;

uses(DatabaseTransactions::class);

beforeEach(function () {
    config(['wm-package.super_admin_emails' => ['superadmin@test.com']]);

    RolesAndPermissionsService::seedDatabase();
});

it('Nova User resource extends AbstractUserResource', function () {
    expect(NovaUser::class)->toExtend(AbstractUserResource::class);
});

it('Nova User resource fields include RoleBooleanGroup', function () {
    $user = User::factory()->create(['email' => 'superadmin@test.com']);
    Auth::login($user);

    $request = NovaRequest::create('/', 'GET');
    $request->setUserResolver(fn () => $user);

    $resource = new NovaUser($user);
    $fields = $resource->fields($request);

    $roleField = collect($fields)->first(fn ($f) => $f instanceof RoleBooleanGroup);

    expect($roleField)->not->toBeNull();
});

it('role field is readonly for non-super-admin', function () {
    $admin = User::factory()->create(['email' => 'admin@test.com']);
    $admin->assignRole('Administrator');
    Auth::login($admin);

    $request = NovaRequest::create('/', 'GET');
    $request->setUserResolver(fn () => $admin);

    $resource = new NovaUser($admin);
    $fields = $resource->fields($request);
    $roleField = collect($fields)->first(fn ($f) => $f instanceof RoleBooleanGroup);

    expect($roleField->isReadonly($request))->toBeTrue();
});

it('role field is editable for super-admin', function () {
    $superAdmin = User::factory()->create(['email' => 'superadmin@test.com']);
    $superAdmin->assignRole('Administrator');
    Auth::login($superAdmin);

    $request = NovaRequest::create('/', 'GET');
    $request->setUserResolver(fn () => $superAdmin);

    $resource = new NovaUser($superAdmin);
    $fields = $resource->fields($request);
    $roleField = collect($fields)->first(fn ($f) => $f instanceof RoleBooleanGroup);

    expect($roleField->isReadonly($request))->toBeFalse();
});

it('super-admin can change another users role', function () {
    $superAdmin = User::factory()->create(['email' => 'superadmin@test.com']);
    $superAdmin->assignRole('Administrator');

    $targetUser = User::factory()->create();
    $targetUser->assignRole('Validator');

    $request = NovaRequest::create('/', 'POST', [
        'roles' => json_encode(['Administrator' => true, 'Validator' => false]),
    ]);
    $request->setUserResolver(fn () => $superAdmin);

    Auth::login($superAdmin);
    $resource = new NovaUser($targetUser);
    $fields = $resource->fields($request);
    $roleField = collect($fields)->first(fn ($f) => $f instanceof RoleBooleanGroup);

    $roleField->fill($request, $targetUser);
    $targetUser->refresh();

    expect($targetUser->hasRole('Administrator'))->toBeTrue()
        ->and($targetUser->hasRole('Validator'))->toBeFalse();
});

it('super-admin cannot remove their own Administrator role', function () {
    $superAdmin = User::factory()->create(['email' => 'superadmin@test.com']);
    $superAdmin->assignRole('Administrator');

    $request = NovaRequest::create('/', 'POST', [
        'roles' => json_encode(['Administrator' => false, 'Validator' => true]),
    ]);
    $request->setUserResolver(fn () => $superAdmin);

    Auth::login($superAdmin);
    $resource = new NovaUser($superAdmin);
    $fields = $resource->fields($request);
    $roleField = collect($fields)->first(fn ($f) => $f instanceof RoleBooleanGroup);

    $roleField->fill($request, $superAdmin);
    $superAdmin->refresh();

    expect($superAdmin->hasRole('Administrator'))->toBeTrue();
});
