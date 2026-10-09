<?php

namespace Modules\Api\Gateway\Methods;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Api\Gateway\GatewayMethod;
use Modules\Api\Models\ApiClient;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantBranch;

/**
 * pos.merchant.list: shops where a gift card can be used: active merchants with their active
 * branches (the branch id goes into pos.giftcard.use). Never branch codes or payouts.
 */
class ListMerchants implements GatewayMethod
{
    public function rules(): array
    {
        return [];
    }

    public function handle(array $input, ApiClient $client): array
    {
        $merchants = Merchant::query()->active()->orderBy('name')
            ->with(['branches' => fn (HasMany $q) => $q->active()->orderBy('name')])
            ->get();

        return ['items' => $merchants->map(fn (Merchant $merchant) => [
            'id' => $merchant->id,
            'name' => $merchant->name,
            'phone' => $merchant->phone,
            'branches' => $merchant->branches->map(fn (MerchantBranch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'address' => $branch->address,
                'phone' => $branch->phone,
            ])->all(),
        ])->all()];
    }
}
