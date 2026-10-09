<?php

namespace Modules\LogViewer\Providers;

use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Modules\LogViewer\Services\LogFileRepository;
use Modules\LogViewer\Services\LogParser;
use Modules\LogViewer\Services\LogReader;
use Nwidart\Modules\Support\ModuleServiceProvider;

class LogViewerServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'LogViewer';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'logviewer';

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

        $this->app->bind(LogFileRepository::class, fn () => new LogFileRepository(
            config('logviewer.path'),
            config('logviewer.prefix'),
        ));
        $this->app->bind(LogReader::class, fn ($app) => new LogReader(
            $app->make(LogParser::class),
            config('logviewer.max_bytes'),
        ));
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->make(MenuRegistry::class)->add(new MenuItem(
            label: 'Logs',
            route: 'admin.logs.index',
            icon: 'fas fa-clipboard-list',
            section: 'Log Management',
            permission: 'view-logviewer',
            order: 95,
        ));
    }
}
