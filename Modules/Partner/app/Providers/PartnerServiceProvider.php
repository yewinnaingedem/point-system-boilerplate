<?php

namespace Modules\Partner\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Nwidart\Modules\Support\ModuleServiceProvider;

class PartnerServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Partner';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'partner';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        RateLimiter::for('partner', fn (Request $request) => Limit::perMinute(config('partner.rate_per_minute'))->by('partner:'.$request->ip()));
    }
}
