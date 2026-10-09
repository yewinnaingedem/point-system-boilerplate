<?php

namespace Modules\Api\Gateway\Methods;

use Modules\Api\Gateway\GatewayCustomers;
use Modules\Api\Gateway\GatewayMethod;
use Modules\Api\Models\ApiClient;
use Modules\Customer\Http\Resources\TierEventResource;
use Modules\Loyalty\Models\TierEvent;

/** pos.tier.history: enrolled, upgraded, requalified, protected, demoted, newest first. */
class TierHistory implements GatewayMethod
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
            $customer->tierEvents()->latest('occurred_at')->latest('id')->getQuery(),
            $input['page'] ?? null,
            fn (TierEvent $event) => (new TierEventResource($event))->resolve(),
        );
    }
}
