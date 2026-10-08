<?php

namespace Modules\Loyalty\Exceptions;

use DomainException;

class InsufficientPoints extends DomainException
{
    public function __construct(public readonly int $balance, public readonly int $required)
    {
        parent::__construct("Not enough points: {$balance} available, {$required} needed.");
    }
}
