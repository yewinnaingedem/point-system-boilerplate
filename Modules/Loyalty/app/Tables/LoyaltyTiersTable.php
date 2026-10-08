<?php

namespace Modules\Loyalty\Tables;

use Illuminate\Http\JsonResponse;
use Modules\Loyalty\Models\LoyaltyTier;
use Modules\Loyalty\Services\TierConfigService;
use Yajra\DataTables\DataTables;

/**
 * Rows for the Loyalty Tiers table, always in tier order (Silver first). There are only four
 * tiers, so the collection engine is used and the browser can't re-sort them.
 */
class LoyaltyTiersTable
{
    private const COLUMNS = ['tier', 'threshold', 'guarantee', 'members', 'color', 'actions'];

    public function __construct(
        private readonly DataTables $datatables,
        private readonly TierConfigService $config,
    ) {}

    public function response(): JsonResponse
    {
        $members = $this->config->memberCounts();

        return $this->datatables->collection($this->config->tiers())
            ->addColumn('tier', fn (LoyaltyTier $tier) => view('loyalty::partials.tier-badge', ['tier' => $tier])->render())
            ->addColumn('threshold', fn (LoyaltyTier $tier) => $tier->tier_level->isBase()
                ? __('Default for every member')
                : money($tier->spending_threshold))
            ->addColumn('guarantee', fn (LoyaltyTier $tier) => $tier->guarantee_months > 0 && ! $tier->tier_level->isBase()
                ? trans_choice('{1} :count month|[2,*] :count months', $tier->guarantee_months)
                : __('None'))
            ->addColumn('members', fn (LoyaltyTier $tier) => number_format($members[$tier->tier_level->value] ?? 0))
            ->addColumn('color', fn (LoyaltyTier $tier) => view('loyalty::cells.color', ['tier' => $tier])->render())
            ->addColumn('actions', fn (LoyaltyTier $tier) => view('loyalty::cells.actions', ['tier' => $tier])->render())
            ->rawColumns(['tier', 'color', 'actions'])
            ->only(self::COLUMNS)
            ->toJson();
    }
}
