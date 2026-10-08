<?php

namespace Modules\GiftCard\Exceptions;

use DomainException;

/** An exchange that can't go ahead; `reason` is a stable key the app can switch on. */
class ExchangeRejected extends DomainException
{
    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }
}
