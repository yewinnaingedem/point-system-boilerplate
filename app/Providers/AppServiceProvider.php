<?php

namespace App\Providers;

use App\Enums\SystemRole;
use App\Models\User;
use App\Support\Menu\MenuRegistry;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MenuRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // AdminLTE 3 is built on Bootstrap 4.
        Paginator::useBootstrapFour();

        // Administrator holds every permission, including those added by future modules.
        // Checks about a specific record (policies: "not yourself", "must be active", ...) still
        // run for administrators, so only plain permission checks (no arguments) are short-cut.
        Gate::before(fn (User $user, string $ability, array $arguments) => $arguments === [] && $user->hasRole(SystemRole::Administrator->value) ? true : null);

        View::composer('partials.sidebar', function ($view) {
            $view->with('menuSections', $this->app->make(MenuRegistry::class)->sectionsFor(auth()->user()));
        });
    }
}
