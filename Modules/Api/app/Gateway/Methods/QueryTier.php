<?php

namespace Modules\Api\Gateway\Methods;

use Modules\Api\Gateway\GatewayCustomers;
use Modules\Api\Gateway\GatewayMethod;
use Modules\Api\Models\ApiClient;
use Modules\Customer\Http\Resources\CustomerResource;

/** pos.tier.query: current tier, guarantee, this cycle's spending and what the next tier needs. */
class QueryTier implements GatewayMethod
{
    public function __construct(private readonly GatewayCustomers $customers) {}

    public function rules(): array
    {
        return ['external_id' => GatewayCustomers::EXTERNAL_ID];
    }

    public function handle(array $input, ApiClient $client): array
    {
        $customer = $this->customers->find($input['external_id']);

        return [
            'external_id' => $customer->external_id,
            'tier' => (new CustomerResource($customer))->resolve()['tier'],
        ];
    }
}
