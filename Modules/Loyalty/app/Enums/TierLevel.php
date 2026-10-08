<?php

namespace Modules\Loyalty\Enums;

/**
 * The tier hierarchy, lowest first. The order is fixed in code; thresholds and guarantee
 * durations are configuration (loyalty_tiers).
 */
enum TierLevel: string
{
    case Silver = 'silver';
    case Gold = 'gold';
    case Platinum = 'platinum';
    case Diamond = 'diamond';

    /** Every member starts here; it has no threshold and no guarantee. */
    public static function base(): self
    {
        return self::Silver;
    }

    public function rank(): int
    {
        return match ($this) {
            self::Silver => 0,
            self::Gold => 1,
            self::Platinum => 2,
            self::Diamond => 3,
        };
    }

    public function isBase(): bool
    {
        return $this === self::base();
    }

    public function isAbove(self $other): bool
    {
        return $this->rank() > $other->rank();
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
