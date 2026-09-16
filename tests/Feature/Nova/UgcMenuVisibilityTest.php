<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Laravel\Nova\Nova;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Services\RolesAndPermissionsService;

uses(DatabaseTransactions::class);

beforeEach(function () {
    RolesAndPermissionsService::seedDatabase();
});

function ugcSectionVisibleFor(User $user): bool
{
    $request = Request::create('/nova');
    $request->setUserResolver(fn () => $user);

    // Nova::resolveMainMenu() esegue il callback passato a Nova::mainMenu() in
    // NovaServiceProvider e ne ritorna l'array grezzo di MenuSection (verificato in
    // vendor/laravel/nova/src/Nova.php — non richiede boot completo di Nova).
    $menu = collect(Nova::resolveMainMenu($request));
    // MenuSection::$name è una proprietà pubblica (constructor-promoted), non un metodo
    // (verificato in vendor/laravel/nova/src/Menu/MenuSection.php).
    $ugcSection = $menu->first(fn ($section) => property_exists($section, 'name') && (string) $section->name === 'UGC');

    return $ugcSection !== null && $ugcSection->authorizedToSee($request);
}

it('always shows the UGC section to Administrator', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Administrator');

    expect(ugcSectionVisibleFor($admin))->toBeTrue();
});

it('always shows the UGC section to Validator, even without any UGC-enabled app', function () {
    $validator = User::factory()->create();
    $validator->assignRole('Validator');

    expect(ugcSectionVisibleFor($validator))->toBeTrue();
});

it('shows the UGC section to an Editor whose app has UGC enabled', function () {
    $editor = User::factory()->create();
    $editor->assignRole('Editor');
    App::factory()->for($editor, 'author')->createQuietly([
        'auth_show_at_startup' => true,
        'geolocation_record_enable' => true,
    ]);

    expect(ugcSectionVisibleFor($editor))->toBeTrue();
});

it('hides the UGC section from an Editor whose app does not have UGC enabled', function () {
    $editor = User::factory()->create();
    $editor->assignRole('Editor');
    App::factory()->for($editor, 'author')->createQuietly([
        'auth_show_at_startup' => false,
        'geolocation_record_enable' => false,
    ]);

    expect(ugcSectionVisibleFor($editor))->toBeFalse();
});

it('hides the UGC section from an Editor with no app at all, without throwing', function () {
    $editor = User::factory()->create();
    $editor->assignRole('Editor');

    expect(fn () => ugcSectionVisibleFor($editor))->not->toThrow(Throwable::class);
    expect(ugcSectionVisibleFor($editor))->toBeFalse();
});

it('shows the UGC section to an Editor with multiple apps if at least one has UGC enabled', function () {
    $editor = User::factory()->create();
    $editor->assignRole('Editor');
    App::factory()->for($editor, 'author')->createQuietly([
        'auth_show_at_startup' => false,
        'geolocation_record_enable' => false,
    ]);
    App::factory()->for($editor, 'author')->createQuietly([
        'auth_show_at_startup' => true,
        'geolocation_record_enable' => true,
    ]);

    expect(ugcSectionVisibleFor($editor))->toBeTrue();
});
