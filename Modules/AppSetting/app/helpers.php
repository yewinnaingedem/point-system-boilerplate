<?php

use Modules\AppSetting\Services\SettingService;

if (! function_exists('setting')) {
    /**
     * Read an application setting: setting('company_name'), or setting() for the service.
     */
    function setting(?string $key = null, mixed $default = null): mixed
    {
        $service = app(SettingService::class);

        return $key === null ? $service : $service->get($key, $default);
    }
}

if (! function_exists('money')) {
    /**
     * Format an amount with the configured currency symbol and position.
     */
    function money(float|int|string|null $amount, int $decimals = 0): string
    {
        $number = number_format((float) $amount, $decimals);
        $symbol = setting('currency_symbol');

        return setting('currency_position') === 'before' ? "{$symbol} {$number}" : "{$number} {$symbol}";
    }
}
