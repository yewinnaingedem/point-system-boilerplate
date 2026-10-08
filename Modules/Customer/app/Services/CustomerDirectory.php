<?php

namespace Modules\Customer\Services;

use Illuminate\Database\ConnectionInterface;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Services\TierQualificationEngine;

/**
 * Finds or creates a customer from the partner project's data (SSO sign-in, partner API).
 * The partner owns name / email / phone; ours are overwritten with what it sends. New
 * customers are enrolled at the base tier (Silver).
 */
class CustomerDirectory
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TierQualificationEngine $tiers,
    ) {}

    /**
     * @param  array{external_id: string, name: string, email?: ?string, phone?: ?string}  $profile
     * @param  array<string, mixed>  $extra  other attributes to set (e.g. last_login_at)
     * @return array{0: Customer, 1: bool} the customer and whether it was just created
     */
    public function upsert(array $profile, array $extra = []): array
    {
        [$customer, $created] = $this->db->transaction(function () use ($profile, $extra) {
            $customer = Customer::query()->where('external_id', $profile['external_id'])->lockForUpdate()->first();
            $created = $customer === null;
            $customer ??= new Customer(['external_id' => $profile['external_id'], 'is_active' => true]);

            $customer->fill([
                'name' => mb_substr($profile['name'], 0, 150),
                'email' => isset($profile['email']) ? mb_substr((string) $profile['email'], 0, 150) : null,
                'phone' => isset($profile['phone']) ? mb_substr((string) $profile['phone'], 0, 50) : null,
                ...$extra,
            ])->save();

            return [$customer, $created];
        });

        if ($created) {
            $this->tiers->enrol($customer->id); // starts at Silver, "enrolled" in the tier history
        }

        return [$customer, $created];
    }
}
