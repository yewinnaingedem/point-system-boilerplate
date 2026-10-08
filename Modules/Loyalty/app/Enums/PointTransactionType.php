<?php

namespace Modules\Loyalty\Enums;

enum PointTransactionType: string
{
    /** Points earned (e.g. from a sale; awarded by the Sales module once it exists). */
    case Earn = 'earn';

    /** Points spent on a merchant reward. */
    case Redeem = 'redeem';

    /** Manual correction by an administrator, either direction. */
    case Adjust = 'adjust';

    /** A redemption undone: its points returned to the lots they came from. */
    case Reversal = 'reversal';

    /** Points that reached their expiry date unspent. */
    case Expire = 'expire';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Earn => 'success',
            self::Redeem => 'primary',
            self::Adjust => 'secondary',
            self::Reversal => 'warning',
            self::Expire => 'danger',
        };
    }
}
