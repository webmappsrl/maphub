<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Wm\WmPackage\Services\RolesAndPermissionsService;

uses(DatabaseTransactions::class);

beforeEach(function () {
    RolesAndPermissionsService::seedDatabase();
});

it('denies viewNova to Guest', function () {
    $user = User::factory()->create();
    $user->assignRole('Guest');

    expect(Gate::forUser($user)->allows('viewNova'))->toBeFalse();
});

it('allows viewNova to Administrator, Editor and Validator', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);

    expect(Gate::forUser($user)->allows('viewNova'))->toBeTrue();
})->with(['Administrator', 'Editor', 'Validator']);

it('denies viewNova to a user without any role', function () {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows('viewNova'))->toBeFalse();
});
