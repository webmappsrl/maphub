<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Wm\WmPackage\Services\RolesAndPermissionsService;

uses(DatabaseTransactions::class);

beforeEach(function () {
    RolesAndPermissionsService::seedDatabase();
});

it('rejects Nova web login for Guest', function () {
    $user = User::factory()->create(['password' => Hash::make('password123')]);
    $user->assignRole('Guest');

    $this->post('/nova/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    expect(Auth::guard('web')->check())->toBeFalse();
});

it('allows Nova web login for Editor', function () {
    $user = User::factory()->create(['password' => Hash::make('password123')]);
    $user->assignRole('Editor');

    $this->post('/nova/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    expect(Auth::guard('web')->check())->toBeTrue();
});

it('allows Nova web login for Validator and Administrator', function (string $role) {
    $user = User::factory()->create(['password' => Hash::make('password123')]);
    $user->assignRole($role);

    $this->post('/nova/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    expect(Auth::guard('web')->check())->toBeTrue();
})->with(['Validator', 'Administrator']);

it('allows API JWT login for Guest', function () {
    $user = User::factory()->create(['password' => Hash::make('password123')]);
    $user->assignRole('Guest');

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['access_token']);
});

it('allows Guest to complete the password reset flow', function () {
    $user = User::factory()->create();
    $user->assignRole('Guest');

    $emailResponse = $this->post('/nova/password/email', ['email' => $user->email]);
    $emailResponse->assertSessionHasNoErrors();

    $token = Password::createToken($user);

    $resetResponse = $this->post('/nova/password/reset', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $resetResponse->assertSessionHasNoErrors();
    expect(Auth::guard('web')->check())->toBeFalse();
    expect(Hash::check('newpassword123', $user->fresh()->password))->toBeTrue();
});

it('allows Guest to change password via API with JWT', function () {
    $user = User::factory()->create(['password' => Hash::make('password123')]);
    $user->assignRole('Guest');

    $token = auth('api')->login($user);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'app-id' => 'it.webmapp.test',
    ])->postJson('/api/auth/user', ['password' => 'newpassword456']);

    $response->assertOk();
    expect(Hash::check('newpassword456', $user->fresh()->password))->toBeTrue();
});

it('allows a user with multiple roles including access-nova to access Nova', function () {
    $user = User::factory()->create(['password' => Hash::make('password123')]);
    $user->assignRole(['Guest', 'Editor']);

    $this->post('/nova/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    expect(Auth::guard('web')->check())->toBeTrue();
});
