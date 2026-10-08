<?php

namespace Modules\Merchant\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Modules\Merchant\Http\Requests\MerchantTableRequest;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantReward;
use Yajra\DataTables\DataTables;

class RewardsTable
{
    private const ORDERABLE = ['name', 'points_cost'];

    private const COLUMNS = ['reward', 'points', 'payout', 'status', 'actions'];

    public function __construct(private readonly DataTables $datatables) {}

    public function response(MerchantTableRequest $request, Merchant $merchant): JsonResponse
    {
        $search = $request->searchTerm();

        return $this->datatables->eloquent($merchant->rewards()->getQuery())
            ->filter(fn (Builder $q) => $q->when($search, fn (Builder $q) => $q->where('name', 'like', "{$search}%")))
            ->whitelist(self::ORDERABLE)
            ->addColumn('reward', fn (MerchantReward $reward) => view('merchant::cells.reward', ['reward' => $reward])->render())
            ->addColumn('points', fn (MerchantReward $reward) => number_format($reward->points_cost))
            ->addColumn('payout', fn (MerchantReward $reward) => view('merchant::cells.payout', ['reward' => $reward, 'merchant' => $merchant])->render())
            ->addColumn('status', fn (MerchantReward $reward) => view('merchant::cells.active', ['active' => $reward->is_active])->render())
            ->addColumn('actions', fn (MerchantReward $reward) => view('merchant::cells.reward-actions', ['reward' => $reward])->render())
            ->rawColumns(['reward', 'payout', 'status', 'actions'])
            ->only(self::COLUMNS)
            ->toJson();
    }
}
