<?php

namespace App\Providers;

use App\Listeners\CheckCacheHealth;
use App\Listeners\CheckDatabaseHealth;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Wm\WmPackage\Policies\PermissionPolicy;
use Wm\WmPackage\Policies\RolePolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);

        Event::listen(DiagnosingHealth::class, CheckDatabaseHealth::class);
        Event::listen(DiagnosingHealth::class, CheckCacheHealth::class);
    }
}
