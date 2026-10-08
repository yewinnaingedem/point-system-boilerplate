<?php

namespace Modules\Merchant\Http\Requests;

use App\Http\Requests\DataTableRequest;
use Modules\Merchant\Enums\RedemptionStatus;

class RedemptionTableRequest extends DataTableRequest
{
    public const SETTLEMENT = ['unsettled', 'settled'];

    public function authorize(): bool
    {
        return $this->user()?->can('view-redemption') ?? false;
    }

    protected function filterRules(): array
    {
        return [
            'merchant_id' => ['nullable', 'integer', 'exists:merchants,id'],
            'status' => ['nullable', 'in:'.implode(',', array_column(RedemptionStatus::cases(), 'value'))],
            'settlement' => ['nullable', 'in:'.implode(',', self::SETTLEMENT)],
        ];
    }
}
