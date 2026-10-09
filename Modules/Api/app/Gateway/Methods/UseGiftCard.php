<?php

namespace Modules\Api\Gateway\Methods;

use Modules\Api\Gateway\GatewayCustomers;
use Modules\Api\Gateway\GatewayError;
use Modules\Api\Gateway\GatewayMethod;
use Modules\Api\Models\ApiClient;
use Modules\GiftCard\Enums\ExchangeStatus;
use Modules\GiftCard\Exceptions\ExchangeRejected;
use Modules\GiftCard\Http\Resources\ExchangeResource;
use Modules\GiftCard\Models\GiftCardExchange;
use Modules\GiftCard\Services\GiftCardExchangeService;

/**
 * pos.giftcard.use: complete an issued gift card at a merchant branch. Shop staff type the
 * branch's 6-digit code on the customer's phone; the card becomes `used` and its value is owed
 * to that merchant. Retrying the same use returns it again with `replayed`: true.
 */
class UseGiftCard implements GatewayMethod
{
    public function __construct(
        private readonly GatewayCustomers $customers,
        private readonly GiftCardExchangeService $exchanges,
    ) {}

    public function rules(): array
    {
        return [
            'external_id' => GatewayCustomers::EXTERNAL_ID,
            'exchange_id' => ['required', 'integer', 'min:1'],
            'branch_id' => ['required', 'integer', 'min:1'],
            'code' => ['required', 'string', 'digits:6'],
        ];
    }

    public function handle(array $input, ApiClient $client): array
    {
        $customer = $this->customers->active($input['external_id']);
        $exchange = GiftCardExchange::query()->where('customer_id', $customer->id)->find((int) $input['exchange_id'])
            ?? throw GatewayError::notFound('EXCHANGE_NOT_FOUND', __('This customer has no gift card with this id.'));
        $wasUsed = $exchange->status === ExchangeStatus::Used;

        try {
            $exchange = $this->exchanges->useAtBranch($customer, $exchange, (int) $input['branch_id'], $input['code']);
        } catch (ExchangeRejected $e) {
            throw ExchangeGiftCard::rejected($e);
        }

        return [
            'replayed' => $wasUsed,
            ...(new ExchangeResource($exchange->load(['giftCard', 'merchant:id,name', 'branch:id,name'])))->resolve(),
        ];
    }
}
