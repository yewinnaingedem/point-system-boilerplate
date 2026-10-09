<?php

namespace Modules\Api\Gateway\Methods;

use Modules\Api\Gateway\GatewayCustomers;
use Modules\Api\Gateway\GatewayMethod;
use Modules\Api\Models\ApiClient;
use Modules\Customer\Http\Resources\CustomerResource;

/** pos.customer.query: profile, points balance, tier and progress to the next tier. */
class QueryCustomer implements GatewayMethod
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
            'active' => $customer->is_active,
            'customer' => (new CustomerResource($customer))->resolve(),
        ];
    }
}
