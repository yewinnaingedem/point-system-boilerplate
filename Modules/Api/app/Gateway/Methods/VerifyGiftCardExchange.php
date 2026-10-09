<?php

namespace Modules\Api\Gateway\Methods;

use Modules\Api\Gateway\GatewayCustomers;
use Modules\Api\Gateway\GatewayError;
use Modules\Api\Gateway\GatewayMethod;
use Modules\Api\Models\ApiClient;
use Modules\GiftCard\Exceptions\ExchangeRejected;
use Modules\GiftCard\Http\Resources\ExchangeResource;
use Modules\GiftCard\Models\GiftCardExchange;
use Modules\GiftCard\Services\GiftCardExchangeService;

/** pos.giftcard.verify: the 6-digit code emailed for a pending exchange → issued. */
class VerifyGiftCardExchange implements GatewayMethod
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
            'code' => ['required', 'string', 'digits:6'],
        ];
    }

    public function handle(array $input, ApiClient $client): array
    {
        $customer = $this->customers->active($input['external_id']);
        $exchange = GiftCardExchange::query()->where('customer_id', $customer->id)->find((int) $input['exchange_id'])
            ?? throw GatewayError::notFound('EXCHANGE_NOT_FOUND', __('This customer has no exchange with this id.'));

        try {
            $exchange = $this->exchanges->verify($customer, $exchange, $input['code']);
        } catch (ExchangeRejected $e) {
            throw ExchangeGiftCard::rejected($e);
        }

        return (new ExchangeResource($exchange->load('giftCard')))->resolve();
    }
}
