<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Wm\WmPackage\Services\RolesAndPermissionsService;

uses(DatabaseTransactions::class);

beforeEach(function () {
    RolesAndPermissionsService::seedDatabase();
});

it('denies the osm import report route to Guest', function () {
    $user = User::factory()->create();
    $user->assignRole('Guest');

    $response = $this->actingAs($user)->get('/nova-vendor/maphub/osm-import-reports/'.str_repeat('a', 16));

    $response->assertForbidden();
});

it('allows the osm import report route to Editor', function () {
    $user = User::factory()->create();
    $user->assignRole('Editor');

    $response = $this->actingAs($user)->get('/nova-vendor/maphub/osm-import-reports/'.str_repeat('a', 16));

    // Il middleware access-nova non deve bloccare (403); il 404 è atteso perché il token è fittizio.
    expect($response->status())->not->toBe(403);
});
