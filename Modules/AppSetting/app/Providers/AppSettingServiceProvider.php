<?php

namespace Modules\AppSetting\Providers;

use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Modules\AppSetting\Services\SettingService;
use Modules\AppSetting\Support\SettingCatalog;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AppSettingServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'AppSetting';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'appsetting';

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

        $this->app->singleton(SettingCatalog::class);

        $this->app->scoped(SettingService::class, function ($app) {
            $disk = config('appsetting.disk', 'public');

            return new SettingService(
                $app->make(SettingCatalog::class),
                $app['cache.store'],
                $app['db.connection'],
                $app['filesystem']->disk($disk),
                $disk,
            );
        });
    }

    public function boot(): void
    {
        parent::boot();

        $this->applyRuntimeConfig();

        $this->app->make(MenuRegistry::class)->add(new MenuItem(
            label: 'Settings',
            route: 'admin.settings.edit',
            icon: 'fas fa-cogs',
            section: 'Administration',
            permission: 'view-appsetting',
            order: 90,
        ));
    }

    /**
     * Settings that override framework config for this request.
     */
    private function applyRuntimeConfig(): void
    {
        $settings = $this->app->make(SettingService::class);
        $timezone = $settings->get('timezone');

        config(['app.name' => $settings->get('app_name')]);

        if ($timezone && in_array($timezone, timezone_identifiers_list(), true)) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }
    }
}
