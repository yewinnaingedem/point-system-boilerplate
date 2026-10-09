<?php

namespace Modules\Api\Gateway\Methods;

use Modules\Api\Gateway\GatewayCustomers;
use Modules\Api\Gateway\GatewayMethod;
use Modules\Api\Models\ApiClient;
use Modules\Customer\Http\Resources\CustomerResource;
use Modules\Customer\Models\Customer;
use Modules\Customer\Services\CustomerDirectory;

/**
 * pos.customer.register: create the customer (enrolled at Silver) or update their profile.
 * The partner owns name / email / phone: what it sends replaces ours, what it leaves out is kept.
 */
class RegisterCustomer implements GatewayMethod
{
    public function __construct(
        private readonly CustomerDirectory $directory,
        private readonly GatewayCustomers $customers,
    ) {}

    public function rules(): array
    {
        return [
            'external_id' => GatewayCustomers::EXTERNAL_ID,
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'string', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function handle(array $input, ApiClient $client): array
    {
        $existing = Customer::query()->where('external_id', $input['external_id'])->first();
        [$customer, $created] = $this->directory->upsert($this->customers->profile($input, $existing));

        return [
            'created' => $created,
            'active' => $customer->is_active,
            'customer' => (new CustomerResource($customer))->resolve(),
        ];
    }
}
