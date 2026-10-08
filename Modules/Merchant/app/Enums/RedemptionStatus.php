<?php

namespace Modules\Merchant\Enums;

enum RedemptionStatus: string
{
    /** Reward handed over, points taken; owed to the merchant until settled. */
    case Completed = 'completed';

    /** Undone by an administrator: points returned, nothing owed. */
    case Reversed = 'reversed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badge(): string
    {
        return $this === self::Completed ? 'success' : 'secondary';
    }
}
