<?php

namespace Modules\Partner\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

/**
 * The partner API is mounted from Modules/Api/routes/api.php (one place for every /api/v1 route);
 * this module has no routes of its own.
 */
class RouteServiceProvider extends ServiceProvider
{
    public function map(): void {}
}
