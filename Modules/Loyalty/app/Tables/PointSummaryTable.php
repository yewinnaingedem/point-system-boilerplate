<?php

namespace Modules\Loyalty\Tables;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Modules\Loyalty\Models\PointSummary;
use Yajra\DataTables\DataTables;

/**
 * Month-by-month points activity, all customers together (or one customer), read from the
 * pre-aggregated loyalty_point_summaries table: at most one row per customer per month.
 */
class PointSummaryTable
{
    private const MONTHS = 36;

    public function __construct(private readonly DataTables $datatables) {}

    public function response(?int $customerId = null): JsonResponse
    {
        $sums = collect(PointSummary::COLUMNS)->map(fn (string $c) => "sum({$c}) as {$c}")->implode(', ');
        $rows = PointSummary::query()
            ->selectRaw("period, {$sums}")
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->groupBy('period')->orderByDesc('period')->limit(self::MONTHS)->get()
            ->map(function ($row) {
                $in = $row->earned + $row->reversed + $row->adjusted_in;
                $out = $row->redeemed + $row->adjusted_out + $row->expired;

                return [
                    'month' => CarbonImmutable::parse($row->period)->format('M Y'),
                    'earned' => number_format($row->earned),
                    'redeemed' => number_format($row->redeemed),
                    'reversed' => number_format($row->reversed),
                    'adjusted' => ($row->adjusted_in - $row->adjusted_out > 0 ? '+' : '').number_format($row->adjusted_in - $row->adjusted_out),
                    'expired' => number_format($row->expired),
                    'net' => ($in - $out > 0 ? '+' : '').number_format($in - $out),
                ];
            });

        return $this->datatables->collection($rows)->toJson();
    }
}
