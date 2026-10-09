<?php

namespace Modules\Api\Gateway;

use Illuminate\Contracts\Container\Container;

/**
 * Every `method` the gateway answers. To add one: write a GatewayMethod in Gateway/Methods,
 * add it here, document it in docs/gateway-api.md.
 */
class GatewayMethods
{
    /** @var array<string, class-string<GatewayMethod>> */
    public const MAP = [
        'pos.customer.register' => Methods\RegisterCustomer::class,
        'pos.customer.query' => Methods\QueryCustomer::class,
        'pos.tier.query' => Methods\QueryTier::class,
        'pos.tier.history' => Methods\TierHistory::class,
        'pos.point.create' => Methods\CreatePoints::class,
        'pos.point.query' => Methods\QueryPoints::class,
        'pos.giftcard.list' => Methods\ListGiftCards::class,
        'pos.giftcard.exchange' => Methods\ExchangeGiftCard::class,
        'pos.giftcard.verify' => Methods\VerifyGiftCardExchange::class,
        'pos.giftcard.exchanges' => Methods\ListGiftCardExchanges::class,
        'pos.giftcard.use' => Methods\UseGiftCard::class,
        'pos.merchant.list' => Methods\ListMerchants::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function resolve(string $method): ?GatewayMethod
    {
        $class = self::MAP[$method] ?? null;

        return $class === null ? null : $this->container->make($class);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys(self::MAP);
    }
}
