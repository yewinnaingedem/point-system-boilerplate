<?php

namespace Modules\Loyalty\Support;

use Carbon\CarbonImmutable;
use Modules\AppSetting\Services\SettingService;

/**
 * When points earned at a given moment expire (settings Loyalty → point expiry).
 *
 * The earning month counts as the first month only if the points were earned on or before
 * the cutoff day (default the 15th); later than that, counting starts with the next month.
 * Points expire at the end of the last counted month. With 2 months:
 *   earned 10 Jan → Jan + Feb → expire end of Feb (expires_at = 1 Mar 00:00)
 *   earned 20 Jan → Feb + Mar → expire end of Mar (expires_at = 1 Apr 00:00)
 * 0 months = points never expire. A changed setting applies to points earned afterwards.
 */
class PointExpiryPolicy
{
    public const MONTHS_SETTING = 'point_expiry_months';

    public const CUTOFF_SETTING = 'point_expiry_cutoff_day';

    private const DEFAULT_CUTOFF = 15;

    public function __construct(private readonly SettingService $settings) {}

    public function months(): int
    {
        return max(0, (int) $this->settings->get(self::MONTHS_SETTING, 0));
    }

    public function cutoffDay(): int
    {
        return min(28, max(1, (int) $this->settings->get(self::CUTOFF_SETTING, self::DEFAULT_CUTOFF)));
    }

    /** Exclusive expiry moment (start of the month after the last counted one), or null for never. */
    public function expiresAt(CarbonImmutable $earnedAt): ?CarbonImmutable
    {
        $months = $this->months();
        if ($months === 0) {
            return null;
        }

        $earnedAt = $earnedAt->setTimezone(config('app.timezone'));
        $firstCounted = $earnedAt->day <= $this->cutoffDay()
            ? $earnedAt->startOfMonth()
            : $earnedAt->startOfMonth()->addMonthNoOverflow();

        return $firstCounted->addMonthsNoOverflow($months);
    }
}
