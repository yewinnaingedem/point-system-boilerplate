<?php

namespace Modules\Customer\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Modules\Customer\Http\Requests\CustomerTableRequest;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Models\LoyaltyTier;
use Yajra\DataTables\DataTables;

class CustomersTable
{
    private const ORDERABLE = ['id', 'name', 'points_balance', 'last_login_at'];

    private const COLUMNS = ['id', 'customer', 'phone', 'tier', 'points', 'last_login', 'status', 'actions'];

    public function __construct(private readonly DataTables $datatables) {}

    public function response(CustomerTableRequest $request): JsonResponse
    {
        $search = $request->searchTerm();
        $status = $request->validated('status');
        /** @var Collection<string, LoyaltyTier> $tiers */
        $tiers = LoyaltyTier::query()->get()->keyBy(fn (LoyaltyTier $tier) => $tier->tier_level->value);

        $query = Customer::query()
            ->select('customers.*')
            ->with('tierStatus:id,customer_id,current_tier')
            ->withSum('pointAccount as points_balance', 'balance')
            ->when($status, fn (Builder $q) => $q->where('is_active', $status === 'active'))
            ->when($request->validated('tier'), fn (Builder $q, string $tier) => $q->whereHas('tierStatus', fn (Builder $s) => $s->where('current_tier', $tier)));

        return $this->datatables->eloquent($query)
            ->filter(fn (Builder $q) => $q->search($search))
            ->whitelist(self::ORDERABLE)
            ->addColumn('customer', fn (Customer $c) => view('customer::customers.cells.customer', ['customer' => $c])->render())
            ->editColumn('phone', fn (Customer $c) => $c->phone ?: '—')
            ->addColumn('tier', fn (Customer $c) => ($tier = $tiers->get($c->tierStatus?->current_tier?->value))
                ? view('loyalty::partials.tier-badge', ['tier' => $tier])->render() : '—')
            ->addColumn('points', fn (Customer $c) => number_format((int) $c->points_balance))
            ->addColumn('last_login', fn (Customer $c) => $c->last_login_at?->diffForHumans() ?? __('Never'))
            ->addColumn('status', fn (Customer $c) => view('customer::customers.cells.status', ['active' => $c->is_active])->render())
            ->addColumn('actions', fn (Customer $c) => view('customer::customers.cells.actions', ['customer' => $c])->render())
            ->rawColumns(['customer', 'tier', 'status', 'actions'])
            ->only(self::COLUMNS)
            ->toJson();
    }
}
