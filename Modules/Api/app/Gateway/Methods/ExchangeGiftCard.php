<?php

namespace Modules\Api\Gateway\Methods;

use Modules\Api\Gateway\GatewayCustomers;
use Modules\Api\Gateway\GatewayError;
use Modules\Api\Gateway\GatewayMethod;
use Modules\Api\Models\ApiClient;
use Modules\GiftCard\Exceptions\ExchangeRejected;
use Modules\GiftCard\Http\Resources\ExchangeResource;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Services\GiftCardExchangeService;

/**
 * pos.giftcard.exchange: spend points on a gift card. Issued at once (status `issued`, with the
 * code), or `pending` when the card needs the emailed code: then call pos.giftcard.verify.
 */
class ExchangeGiftCard implements GatewayMethod
{
    public function __construct(
        private readonly GatewayCustomers $customers,
        private readonly GiftCardExchangeService $exchanges,
    ) {}

    public function rules(): array
    {
        return ['external_id' => GatewayCustomers::EXTERNAL_ID, 'gift_card_id' => ['required', 'integer', 'min:1']];
    }

    public function handle(array $input, ApiClient $client): array
    {
        $customer = $this->customers->active($input['external_id']);
        $card = GiftCard::query()->find((int) $input['gift_card_id'])
            ?? throw GatewayError::notFound('GIFT_CARD_NOT_FOUND', __('No gift card with this id.'));

        try {
            $exchange = $this->exchanges->request($customer, $card);
        } catch (ExchangeRejected $e) {
            throw self::rejected($e);
        }

        return (new ExchangeResource($exchange->load('giftCard')))->resolve();
    }

    /** NOT_ENOUGH_POINTS, OUT_OF_STOCK, TIER, LIMIT_REACHED, WRONG_CODE, ... (the service's `reason`). */
    public static function rejected(ExchangeRejected $e): GatewayError
    {
        return new GatewayError(strtoupper($e->reason), $e->getMessage());
    }
}
