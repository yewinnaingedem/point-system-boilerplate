<?php

namespace Modules\Merchant\Exceptions;

use DomainException;

/**
 * A redemption that can't go ahead. $field names the input it concerns (for a 422 response),
 * $retryAfter is set when the member is locked out after too many wrong codes.
 */
class RedemptionRejected extends DomainException
{
    public function __construct(string $message, public readonly string $field, public readonly ?int $retryAfter = null)
    {
        parent::__construct($message);
    }

    public static function unavailable(string $field, string $message): self
    {
        return new self($message, $field);
    }

    public static function wrongCode(int $attemptsLeft): self
    {
        return new self(trans_choice('{0} The code is not correct.|{1} The code is not correct. 1 try left.|[2,*] The code is not correct. :count tries left.', $attemptsLeft), 'code');
    }

    public static function lockedOut(int $seconds): self
    {
        return new self(__('Too many wrong codes. Try again in :minutes minutes.', ['minutes' => (int) ceil($seconds / 60)]), 'code', $seconds);
    }
}
