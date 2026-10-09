<?php

namespace Modules\Loyalty\Tables;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Enums\EarnedPointStatus;
use Modules\Loyalty\Enums\PointTransactionType;
use Modules\Loyalty\Http\Requests\EarnedPointsRequest;
use Modules\Loyalty\Models\PointLot;
use Yajra\DataTables\DataTables;

/**
 * Points → Earned Points: every credit of points (one lot each) for all customers, with what is
 * left of it and when it expires.
 */
class EarnedPointsTable
{
    private const ORDERABLE = ['loyalty_point_lots.earned_at', 'loyalty_point_lots.points', 'loyalty_point_lots.remaining', 'loyalty_point_lots.expires_at'];

    private const COLUMNS = ['earned_at', 'customer', 'points', 'remaining', 'status', 'expires_at', 'source', 'reference', 'note'];

    public function __construct(private readonly DataTables $datatables) {}

    public function response(EarnedPointsRequest $request): JsonResponse
    {
        $now = CarbonImmutable::now();
        $query = self::query($request, $now)->with('customer:id,name,email,phone');
        $search = $request->searchTerm();

        return $this->datatables->eloquent($query)
            // Customer name / email / phone / id, or the award's reference (e.g. the order number), prefix.
            ->filter(fn (Builder $q) => $q->when($search, fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('tx.reference', 'like', "{$search}%")
                ->orWhereIn('loyalty_point_lots.customer_id', Customer::search($search)->select('id'))
                ->orWhereIn('loyalty_point_lots.customer_id', Customer::query()->where('external_id', $search)->select('id')))))
            ->whitelist(self::ORDERABLE)
            ->editColumn('earned_at', fn (PointLot $lot) => $lot->earned_at->format(setting('date_format').' H:i'))
            ->addColumn('customer', fn (PointLot $lot) => view('loyalty::points.cells.member', ['customer' => $lot->customer])->render())
            ->editColumn('points', fn (PointLot $lot) => '<span class="text-success font-weight-bold">+'.number_format($lot->points).'</span>')
            ->editColumn('remaining', fn (PointLot $lot) => number_format($lot->remaining))
            ->addColumn('status', function (PointLot $lot) use ($now) {
                $status = EarnedPointStatus::of($lot->points, $lot->remaining, $lot->expires_at, $now);

                return '<span class="badge badge-'.$status->badge().'">'.e(__($status->label())).'</span>';
            })
            ->editColumn('expires_at', fn (PointLot $lot) => $lot->expires_at
                ? e($lot->expires_at->subSecond()->format(setting('date_format')))
                : '<span class="text-muted">'.e(__('Never')).'</span>')
            ->addColumn('source', fn (PointLot $lot) => $lot->tx_type === PointTransactionType::Earn->value
                ? '<span class="badge badge-success">'.e(__('Awarded')).'</span>'
                : '<span class="badge badge-secondary">'.e(__('Manual')).'</span>')
            ->addColumn('reference', fn (PointLot $lot) => e($lot->tx_reference ?? '—'))
            ->addColumn('note', fn (PointLot $lot) => e($lot->tx_note ?? '—'))
            ->rawColumns(['customer', 'points', 'status', 'expires_at', 'source', 'reference', 'note'])
            ->only(self::COLUMNS)
            ->toJson();
    }

    /** The filtered lots, with their transaction's type, reference and note. Shared with the page's totals. */
    public static function query(EarnedPointsRequest $request, CarbonImmutable $now): Builder
    {
        $query = PointLot::query()
            ->select('loyalty_point_lots.*', 'tx.type as tx_type', 'tx.reference as tx_reference', 'tx.note as tx_note')
            ->join('loyalty_point_transactions as tx', 'tx.id', '=', 'loyalty_point_lots.transaction_id')
            ->when($request->source(), fn (Builder $q, $source) => $q->where('tx.type', $source))
            ->when($request->expiringWithinDays(), fn (Builder $q, int $days) => $q
                ->where('loyalty_point_lots.remaining', '>', 0)
                ->where('loyalty_point_lots.expires_at', '>', $now)
                ->where('loyalty_point_lots.expires_at', '<=', $now->addDays($days)));
        $request->status()?->apply($query, $now);
        $request->period()->apply($query, 'loyalty_point_lots.earned_at');

        return $query;
    }
}
