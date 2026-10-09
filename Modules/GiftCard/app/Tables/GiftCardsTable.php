<?php

namespace Modules\GiftCard\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Modules\GiftCard\Enums\ExchangeStatus;
use Modules\GiftCard\Http\Requests\GiftCardTableRequest;
use Modules\GiftCard\Models\GiftCard;
use Modules\Loyalty\Models\LoyaltyTier;
use Yajra\DataTables\DataTables;

class GiftCardsTable
{
    private const ORDERABLE = ['id', 'name', 'points_cost', 'stock', 'issued_count'];

    private const COLUMNS = ['id', 'card', 'points', 'value', 'tier', 'stock_left', 'limits', 'status', 'actions'];

    public function __construct(private readonly DataTables $datatables) {}

    public function response(GiftCardTableRequest $request): JsonResponse
    {
        $search = $request->searchTerm();
        /** @var Collection<string, LoyaltyTier> $tiers */
        $tiers = LoyaltyTier::query()->get()->keyBy(fn (LoyaltyTier $tier) => $tier->tier_level->value);
        $query = GiftCard::query()->select('gift_cards.*')->with('merchant:id,name')
            ->withCount(['exchanges as issued_count' => fn (Builder $q) => $q->whereIn('status', ExchangeStatus::counted())]);

        return $this->datatables->eloquent($query)
            ->filter(fn (Builder $q) => $q->when($search, fn (Builder $q) => $q->where('name', 'like', "{$search}%")))
            ->whitelist(self::ORDERABLE)
            ->addColumn('card', fn (GiftCard $c) => view('giftcard::cells.card', ['card' => $c])->render())
            ->addColumn('points', fn (GiftCard $c) => number_format($c->points_cost))
            ->addColumn('value', fn (GiftCard $c) => money($c->face_value, 2))
            ->addColumn('tier', fn (GiftCard $c) => $c->min_tier && ($tier = $tiers->get($c->min_tier->value))
                ? view('loyalty::partials.tier-badge', ['tier' => $tier])->render().' <span class="small text-muted">+</span>'
                : '<span class="text-muted">'.e(__('Every tier')).'</span>')
            ->addColumn('stock_left', fn (GiftCard $c) => view('giftcard::cells.stock', ['card' => $c])->render())
            ->addColumn('limits', fn (GiftCard $c) => view('giftcard::cells.limits', ['card' => $c])->render())
            ->addColumn('status', fn (GiftCard $c) => view('giftcard::cells.active', ['active' => $c->is_active])->render())
            ->addColumn('actions', fn (GiftCard $c) => view('giftcard::cells.card-actions', ['card' => $c])->render())
            ->rawColumns(['card', 'tier', 'stock_left', 'limits', 'status', 'actions'])
            ->only(self::COLUMNS)
            ->toJson();
    }
}
