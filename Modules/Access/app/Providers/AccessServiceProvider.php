<?php

namespace Modules\Access\Providers;

use App\Models\User;
use App\Support\Menu\MenuGroup;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Modules\Access\Policies\UserPolicy;
use Modules\Access\Services\ImpersonationService;
use Modules\Access\Services\UserService;
use Modules\Access\Services\UserSessionService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AccessServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Access';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'access';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }

    public function register(): void
    {
        parent::register();

        $this->app->bind(UserSessionService::class, fn ($app) => new UserSessionService(
            $app['db.connection'],
            (string) config('session.driver'),
            (string) config('session.table', 'sessions'),
            request()->hasSession() ? request()->session()->getId() : null,
        ));

        $this->app->bind(UserService::class, fn ($app) => new UserService(
            $app['db.connection'],
            $app['filesystem']->disk('public'),
            $app->make(UserSessionService::class),
        ));

        $this->app->bind(ImpersonationService::class, fn ($app) => new ImpersonationService(
            $app['auth']->guard('web'),
            $app->make(GateContract::class),
        ));
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(User::class, UserPolicy::class);

        // Deleted users are only reachable through these two parameters (restore / delete permanently).
        Route::bind('deletedUser', fn (string $id) => User::onlyTrashed()->findOrFail($id));

        // Banner shown on every admin page while "Login as" is active.
        View::composer('components.layouts.admin', function ($view) {
            $session = request()->session();
            $impersonation = $this->app->make(ImpersonationService::class);
            $view->with('impersonatorName', $impersonation->isImpersonating($session) ? $impersonation->impersonatorName($session) : null);
        });

        $this->app->make(MenuRegistry::class)
            ->addGroup(new MenuGroup('access', 'Access Management', 'fas fa-user-lock', 'Administration', 10))
            ->add(new MenuItem('Users', 'admin.access.users.index', 'fas fa-users', permission: 'view-user', order: 10, parent: 'access'))
            ->add(new MenuItem('Roles', 'admin.access.roles.index', 'fas fa-user-shield', permission: 'view-role', order: 20, parent: 'access'))
            ->add(new MenuItem('Permissions', 'admin.access.permissions.index', 'fas fa-key', permission: 'view-permission', order: 30, parent: 'access'));
    }
}
