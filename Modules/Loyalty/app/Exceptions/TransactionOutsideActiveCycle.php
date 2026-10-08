<?php

namespace Modules\Loyalty\Exceptions;

use Carbon\CarbonImmutable;
use DomainException;

/**
 * A transaction dated before the member's active cycle. That cycle is closed and its tier
 * outcome already applied, so the spending can't count towards qualification any more.
 */
class TransactionOutsideActiveCycle extends DomainException
{
    public static function for(int $customerId, CarbonImmutable $date, CarbonImmutable $cycleStart): self
    {
        return new self("Transaction of {$date->toDateTimeString()} for customer {$customerId} is before the active cycle that started {$cycleStart->toDateTimeString()}.");
    }
}
