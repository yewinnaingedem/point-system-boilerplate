<?php

namespace Modules\GiftCard\Enums;

enum ClaimStatus: string
{
    case Submitted = 'submitted'; // waiting for our staff; the merchant can still edit or delete it
    case Paid = 'paid';           // we paid the merchant; its cards are settled
    case Rejected = 'rejected';   // not paid; its cards were released and can be claimed again

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::Paid => 'success',
            self::Rejected => 'danger',
        };
    }
}
