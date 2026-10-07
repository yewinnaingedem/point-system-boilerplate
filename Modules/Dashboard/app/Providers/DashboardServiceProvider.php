<?php

namespace Modules\Dashboard\Providers;

use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Nwidart\Modules\Support\ModuleServiceProvider;

class DashboardServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Dashboard';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'dashboard';

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

    public function boot(): void
    {
        parent::boot();

        $this->app->make(MenuRegistry::class)->add(
            new MenuItem('Dashboard', 'admin.dashboard', 'fas fa-tachometer-alt', 'Main', order: 0, activePattern: 'admin.dashboard'),
        );
    }
}
