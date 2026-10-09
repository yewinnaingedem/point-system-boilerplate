<?php

namespace Modules\GiftCard\Enums;

enum ExchangeStatus: string
{
    case Pending = 'pending';     // waiting for the emailed code
    case Issued = 'issued';       // points taken, gift card code given
    case Used = 'used';           // completed at a merchant branch (branch code); owed to that merchant
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
            self::Used => 'info',
            self::Cancelled => 'secondary',
            self::Failed => 'danger',
        };
    }

    /** Statuses that count against stock and the per-customer limit (a used card was issued first). */
    public static function counted(): array
    {
        return [self::Issued, self::Used];
    }

    /** What the customer sees under "my gift cards". */
    public static function owned(): array
    {
        return [self::Issued, self::Used, self::Cancelled];
    }
}
