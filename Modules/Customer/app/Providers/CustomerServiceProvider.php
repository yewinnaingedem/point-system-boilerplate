<?php

namespace Modules\Customer\Providers;

use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CustomerServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Customer';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'customer';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /** Customer sign-ins per minute per IP: many phones can share one mobile-network IP. */
    private const SSO_PER_MINUTE = 30;

    public function boot(): void
    {
        parent::boot();

        RateLimiter::for('customer-sso', fn (Request $request) => Limit::perMinute(self::SSO_PER_MINUTE)->by($request->ip()));

        $this->app->make(MenuRegistry::class)->add(new MenuItem(
            label: 'Customers',
            route: 'admin.customers.index',
            icon: 'fas fa-user-tag',
            section: 'Sales',
            permission: 'view-customer',
            order: 40,
        ));
    }
}
