<?php

namespace Modules\Loyalty\Enums;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/** Where one credit of points (a lot) stands: how much is left and whether it can still be spent. */
enum EarnedPointStatus: string
{
    case Unused = 'unused';           // all points still there
    case PartlyUsed = 'partly_used';  // some spent
    case UsedUp = 'used_up';          // all spent before it expired
    case Expired = 'expired';         // its validity ended (what was left is gone)

    public function label(): string
    {
        return match ($this) {
            self::Unused => 'Unused',
            self::PartlyUsed => 'Partly used',
            self::UsedUp => 'Used up',
            self::Expired => 'Expired',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Unused => 'success',
            self::PartlyUsed => 'info',
            self::UsedUp => 'secondary',
            self::Expired => 'danger',
        };
    }

    public static function of(int $points, int $remaining, ?CarbonImmutable $expiresAt, CarbonImmutable $now): self
    {
        return match (true) {
            $expiresAt !== null && $expiresAt->lte($now) => self::Expired,
            $remaining === 0 => self::UsedUp,
            $remaining < $points => self::PartlyUsed,
            default => self::Unused,
        };
    }

    /** The same rule as of(), as a query filter on loyalty_point_lots. */
    public function apply(Builder $query, CarbonImmutable $now): void
    {
        $valid = fn (Builder $q) => $q->whereNull('loyalty_point_lots.expires_at')->orWhere('loyalty_point_lots.expires_at', '>', $now);

        match ($this) {
            self::Expired => $query->where('loyalty_point_lots.expires_at', '<=', $now),
            self::UsedUp => $query->where('loyalty_point_lots.remaining', 0)->where($valid),
            self::PartlyUsed => $query->where('loyalty_point_lots.remaining', '>', 0)
                ->whereColumn('loyalty_point_lots.remaining', '<', 'loyalty_point_lots.points')->where($valid),
            self::Unused => $query->whereColumn('loyalty_point_lots.remaining', 'loyalty_point_lots.points')->where($valid),
        };
    }
}
