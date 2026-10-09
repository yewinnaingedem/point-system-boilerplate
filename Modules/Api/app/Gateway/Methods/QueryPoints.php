<?php

namespace Modules\Api\Gateway\Methods;

use Modules\Api\Gateway\GatewayCustomers;
use Modules\Api\Gateway\GatewayMethod;
use Modules\Api\Models\ApiClient;
use Modules\Loyalty\Models\PointTransaction;
use Modules\Loyalty\Support\PointStatement;

/** pos.point.query: balance, next expiry, points by expiry, last 12 months, and one page of history. */
class QueryPoints implements GatewayMethod
{
    public function __construct(
        private readonly GatewayCustomers $customers,
        private readonly PointStatement $statement,
    ) {}

    public function rules(): array
    {
        return ['external_id' => GatewayCustomers::EXTERNAL_ID, 'page' => GatewayCustomers::PAGE];
    }

    public function handle(array $input, ApiClient $client): array
    {
        $customer = $this->customers->find($input['external_id']);

        return [
            'external_id' => $customer->external_id,
            ...$this->statement->for($customer->id),
            'history' => $this->customers->page(
                PointTransaction::query()->where('customer_id', $customer->id)->latest('id'),
                $input['page'] ?? null,
                fn (PointTransaction $tx) => [
                    'type' => $tx->type->value,
                    'points' => $tx->points,
                    'balance_after' => $tx->balance_after,
                    'reference' => $tx->reference,
                    'note' => $tx->note,
                    'at' => $tx->created_at->toIso8601String(),
                ],
            ),
        ];
    }
}
