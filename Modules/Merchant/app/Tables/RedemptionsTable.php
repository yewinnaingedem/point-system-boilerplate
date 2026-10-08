<?php

namespace Modules\Merchant\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Modules\Customer\Models\Customer;
use Modules\Merchant\Http\Requests\RedemptionTableRequest;
use Modules\Merchant\Models\Redemption;
use Yajra\DataTables\DataTables;

class RedemptionsTable
{
    private const ORDERABLE = ['redeemed_at', 'points', 'payout_amount'];

    private const COLUMNS = ['reference', 'date', 'member', 'shop', 'reward', 'points', 'payout', 'status', 'actions'];

    public function __construct(private readonly DataTables $datatables) {}

    public function response(RedemptionTableRequest $request): JsonResponse
    {
        $search = $request->searchTerm();
        $settlement = $request->validated('settlement');

        $query = Redemption::query()
            ->select('merchant_redemptions.*')
            ->with(['customer:id,name,email,phone', 'merchant:id,name', 'branch:id,name'])
            ->when($request->validated('merchant_id'), fn (Builder $q, $id) => $q->where('merchant_id', $id))
            ->when($request->validated('status'), fn (Builder $q, $status) => $q->where('status', $status))
            ->when($settlement === 'unsettled', fn (Builder $q) => $q->whereNull('settlement_id'))
            ->when($settlement === 'settled', fn (Builder $q) => $q->whereNotNull('settlement_id'));

        return $this->datatables->eloquent($query)
            // Reference (exact prefix) or the customer's name / email / phone.
            ->filter(fn (Builder $q) => $q->when($search, fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('reference', 'like', "{$search}%")
                ->orWhereIn('customer_id', Customer::search($search)->select('id')))))
            ->whitelist(self::ORDERABLE)
            ->addColumn('date', fn (Redemption $r) => $r->redeemed_at->format(setting('date_format').' H:i'))
            ->addColumn('member', fn (Redemption $r) => $r->customer?->name ?? __('Deleted customer'))
            ->addColumn('shop', fn (Redemption $r) => "{$r->merchant->name} · {$r->branch->name}")
            ->addColumn('reward', fn (Redemption $r) => $r->reward_name)
            // editColumn, not addColumn: yajra blacklists added names, which would silently stop sorting.
            ->editColumn('points', fn (Redemption $r) => number_format($r->points))
            ->addColumn('payout', fn (Redemption $r) => money($r->payout_amount, 2))
            ->addColumn('status', fn (Redemption $r) => view('merchant::cells.redemption-status', ['redemption' => $r])->render())
            ->addColumn('actions', fn (Redemption $r) => view('merchant::cells.redemption-actions', ['redemption' => $r])->render())
            ->rawColumns(['status', 'actions'])
            ->only(self::COLUMNS)
            ->toJson();
    }
}
