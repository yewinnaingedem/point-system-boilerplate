<?php

namespace Modules\GiftCard\Http\Requests;

use App\Http\Requests\DataTableRequest;
use Modules\GiftCard\Enums\ExchangeStatus;

class GiftCardTableRequest extends DataTableRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->routeIs('admin.gift-card-exchanges.*') ? 'view-giftcardexchange' : 'view-giftcard') ?? false;
    }

    protected function filterRules(): array
    {
        return [
            'status' => ['nullable', 'in:'.implode(',', array_column(ExchangeStatus::cases(), 'value'))],
            'gift_card_id' => ['nullable', 'integer'],
        ];
    }
}
