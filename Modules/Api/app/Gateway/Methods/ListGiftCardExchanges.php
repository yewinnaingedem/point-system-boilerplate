<?php

namespace Modules\Api\Gateway\Methods;

use Modules\Api\Gateway\GatewayCustomers;
use Modules\Api\Gateway\GatewayMethod;
use Modules\Api\Models\ApiClient;
use Modules\GiftCard\Enums\ExchangeStatus;
use Modules\GiftCard\Http\Resources\ExchangeResource;
use Modules\GiftCard\Models\GiftCardExchange;

/** pos.giftcard.exchanges: the customer's gift cards (issued, used and cancelled), newest first. */
class ListGiftCardExchanges implements GatewayMethod
{
    public function __construct(private readonly GatewayCustomers $customers) {}

    public function rules(): array
    {
        return ['external_id' => GatewayCustomers::EXTERNAL_ID, 'page' => GatewayCustomers::PAGE];
    }

    public function handle(array $input, ApiClient $client): array
    {
        $customer = $this->customers->find($input['external_id']);

        return $this->customers->page(
            GiftCardExchange::query()->with(['giftCard.merchant:id,name', 'merchant:id,name', 'branch:id,name'])->where('customer_id', $customer->id)
                ->whereIn('status', ExchangeStatus::owned())->latest('id'),
            $input['page'] ?? null,
            fn (GiftCardExchange $exchange) => (new ExchangeResource($exchange))->resolve(),
        );
    }
}
