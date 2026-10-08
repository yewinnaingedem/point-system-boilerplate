<?php

namespace Modules\Loyalty\Support;

use InvalidArgumentException;

/**
 * Decimal money arithmetic for spending totals (bcmath, 2 places), so thresholds are
 * compared exactly instead of through floats.
 */
final class Amount
{
    public const SCALE = 2;

    public const ZERO = '0.00';

    /** Normalise to a "1234.50" string. */
    public static function of(string|int|float $amount): string
    {
        $value = is_float($amount) ? number_format($amount, self::SCALE, '.', '') : trim((string) $amount);

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Not a valid amount: {$value}");
        }

        return bcadd($value, '0', self::SCALE);
    }

    public static function compare(string $left, string $right): int
    {
        return bccomp($left, $right, self::SCALE);
    }

    public static function subtract(string $left, string $right): string
    {
        return bcsub($left, $right, self::SCALE);
    }

    public static function isPositive(string $amount): bool
    {
        return self::compare($amount, self::ZERO) > 0;
    }
}
