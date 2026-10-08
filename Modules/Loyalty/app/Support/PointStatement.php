<?php

namespace Modules\Loyalty\Support;

use Carbon\CarbonImmutable;
use Modules\Loyalty\Models\PointLot;
use Modules\Loyalty\Models\PointSummary;
use Modules\Loyalty\Services\PointWallet;

/**
 * One customer's points at a glance: balance, what expires when, and month-by-month activity.
 * Shared by the customer API, the partner API and the admin pages, so they always agree.
 */
class PointStatement
{
    private const SUMMARY_MONTHS = 12;

    public function __construct(private readonly PointWallet $wallet) {}

    /**
     * @return array{balance: int, next_expiry: ?array{points: int, expires_on: string}, by_expiry: list<array{points: int, expires_on: ?string}>, months: list<array<string, mixed>>}
     */
    public function for(int $customerId): array
    {
        $balance = $this->wallet->balance($customerId); // expires what is due first
        $now = CarbonImmutable::now();

        // Remaining points grouped by expiry date (the day before the exclusive expires_at).
        $byExpiry = PointLot::query()->where('customer_id', $customerId)->spendable($now)->get()
            ->groupBy(fn (PointLot $lot) => $lot->expires_at?->toDateTimeString() ?? 'never')
            ->map(fn ($lots) => [
                'points' => (int) $lots->sum('remaining'),
                'expires_on' => $lots->first()->expires_at?->subSecond()->toDateString(),
            ])
            ->values()->all();

        $months = PointSummary::query()->where('customer_id', $customerId)
            ->where('period', '>=', $now->startOfMonth()->subMonths(self::SUMMARY_MONTHS - 1)->toDateString())
            ->orderByDesc('period')->get()
            ->map(fn (PointSummary $row) => ['month' => $row->period->format('Y-m')] + $row->only(PointSummary::COLUMNS))
            ->all();

        $next = collect($byExpiry)->first(fn (array $group) => $group['expires_on'] !== null);

        return ['balance' => $balance, 'next_expiry' => $next, 'by_expiry' => $byExpiry, 'months' => $months];
    }
}
