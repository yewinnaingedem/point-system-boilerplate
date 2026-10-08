<?php

namespace Modules\GiftCard\Enums;

enum ExchangeStatus: string
{
    case Pending = 'pending';     // waiting for the emailed code
    case Issued = 'issued';       // points taken, gift card code given
    case Cancelled = 'cancelled'; // by staff: points and stock returned
    case Failed = 'failed';       // verification expired or too many wrong codes

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Issued => 'success',
            self::Cancelled => 'secondary',
            self::Failed => 'danger',
        };
    }

    /** Statuses that count against stock and the per-customer limit. */
    public static function counted(): array
    {
        return [self::Issued];
    }
}
