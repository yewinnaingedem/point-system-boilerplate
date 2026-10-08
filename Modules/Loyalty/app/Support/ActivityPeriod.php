<?php

namespace Modules\Loyalty\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The date range picked on the Points Activity page, in the store's time zone.
 * `end` is exclusive; both null = all time.
 */
final class ActivityPeriod
{
    public const OPTIONS = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'week' => 'Last 7 days',
        'month' => 'This month',
        'last_month' => 'Last month',
        'all' => 'All time',
        'custom' => 'Custom dates',
    ];

    public const DEFAULT = 'today';

    private function __construct(
        public readonly ?CarbonImmutable $start,
        public readonly ?CarbonImmutable $end,
    ) {}

    public static function from(?string $period, ?string $from = null, ?string $to = null): self
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        return match ($period ?? self::DEFAULT) {
            'yesterday' => new self($today->subDay(), $today),
            'week' => new self($today->subDays(6), $today->addDay()),
            'month' => new self($today->startOfMonth(), $today->addDay()),
            'last_month' => new self($today->startOfMonth()->subMonth(), $today->startOfMonth()),
            'all' => new self(null, null),
            'custom' => new self(
                $from ? CarbonImmutable::parse($from, config('app.timezone'))->startOfDay() : null,
                $to ? CarbonImmutable::parse($to, config('app.timezone'))->startOfDay()->addDay() : null,
            ),
            default => new self($today, $today->addDay()),
        };
    }

    /** @param Builder|\Illuminate\Database\Query\Builder $query */
    public function apply($query, string $column = 'created_at'): void
    {
        if ($this->start) {
            $query->where($column, '>=', $this->start);
        }
        if ($this->end) {
            $query->where($column, '<', $this->end);
        }
    }
}
