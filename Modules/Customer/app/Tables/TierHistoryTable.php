<?php

namespace Modules\Customer\Tables;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Models\LoyaltyTier;
use Modules\Loyalty\Models\TierEvent;
use Yajra\DataTables\DataTables;

/** One customer's tier history, newest first. */
class TierHistoryTable
{
    private const ORDERABLE = ['occurred_at'];

    private const COLUMNS = ['date', 'transition', 'from', 'to', 'cycle_spent', 'guarantee'];

    public function __construct(private readonly DataTables $datatables) {}

    public function response(Customer $customer): JsonResponse
    {
        /** @var Collection<string, LoyaltyTier> $tiers */
        $tiers = LoyaltyTier::query()->get()->keyBy(fn (LoyaltyTier $tier) => $tier->tier_level->value);
        $badge = fn ($level) => ($tier = $tiers->get($level?->value)) ? view('loyalty::partials.tier-badge', ['tier' => $tier])->render() : '—';

        return $this->datatables->eloquent($customer->tierEvents()->getQuery())
            ->filter(fn () => null)
            ->whitelist(self::ORDERABLE)
            ->addColumn('date', fn (TierEvent $e) => $e->occurred_at->format(setting('date_format').' H:i'))
            ->addColumn('transition', fn (TierEvent $e) => view('customer::customers.cells.transition', ['transition' => $e->transition])->render())
            ->addColumn('from', fn (TierEvent $e) => $badge($e->from_tier))
            ->addColumn('to', fn (TierEvent $e) => $badge($e->to_tier))
            ->editColumn('cycle_spent', fn (TierEvent $e) => money($e->cycle_spent))
            ->addColumn('guarantee', fn (TierEvent $e) => $e->guarantee_expires_at?->format(setting('date_format')) ?? '—')
            ->rawColumns(['transition', 'from', 'to'])
            ->only(self::COLUMNS)
            ->toJson();
    }
}
