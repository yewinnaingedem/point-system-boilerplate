<?php

namespace Modules\Api\Providers;

use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Modules\Api\Models\ApiClient;
use Modules\Api\Services\ApiTokenService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ApiServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Api';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'api';

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
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        // Expired tokens are already rejected; this just keeps the table small.
        $schedule->command('sanctum:prune-expired --hours=24')->daily();
    }

    public function register(): void
    {
        parent::register();

        $this->app->bind(ApiTokenService::class, fn ($app) => new ApiTokenService(
            $app['db.connection'],
            config('api.token_ttl_days'),
            config('api.max_devices'),
        ));
    }

    public function boot(): void
    {
        parent::boot();

        $this->configureRateLimiting();

        $this->app['router']->model('client', ApiClient::class);

        $this->app->make(MenuRegistry::class)->add(new MenuItem(
            label: 'API Clients',
            route: 'admin.api-clients.index',
            icon: 'fas fa-key',
            section: 'Management',
            permission: 'view-apiclient',
            order: 86,
        ));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api-login', fn (Request $request) => Limit::perMinute(config('api.login_attempts_per_minute'))
            ->by(Str::lower((string) $request->input('login')).'|'.$request->ip()));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(config('api.rate_per_minute'))
            // Staff users and customers have separate id sequences: key by type + id.
            ->by($request->user() ? class_basename($request->user()).':'.$request->user()->getKey() : $request->ip()));
    }
}
