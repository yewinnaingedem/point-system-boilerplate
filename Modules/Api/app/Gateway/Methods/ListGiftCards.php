<?php

namespace Modules\Api\Gateway\Methods;

use Modules\Api\Gateway\GatewayCustomers;
use Modules\Api\Gateway\GatewayMethod;
use Modules\Api\Models\ApiClient;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Services\GiftCardExchangeService;

/** pos.giftcard.list: active gift cards, each with `available` + `reason` for this customer. */
class ListGiftCards implements GatewayMethod
{
    public function __construct(
        private readonly GatewayCustomers $customers,
        private readonly GiftCardExchangeService $exchanges,
    ) {}

    public function rules(): array
    {
        return ['external_id' => GatewayCustomers::EXTERNAL_ID];
    }

    public function handle(array $input, ApiClient $client): array
    {
        $customer = $this->customers->active($input['external_id']);

        return ['items' => GiftCard::query()->active()->with('merchant:id,name')->orderBy('points_cost')->get()->map(fn (GiftCard $card) => [
            'id' => $card->id,
            'merchant' => $card->merchant ? ['id' => $card->merchant->id, 'name' => $card->merchant->name] : null, // null = any partner shop
            'name' => $card->name,
            'description' => $card->description,
            'points' => $card->points_cost,
            'value' => $card->face_value,
            'min_tier' => $card->min_tier?->value,
            'in_stock' => ! $card->isOutOfStock(),
            'requires_verification' => $card->requires_verification,
            'valid_days' => $card->valid_days,
            ...$this->exchanges->availability($customer, $card),
        ])->all()];
    }
}
