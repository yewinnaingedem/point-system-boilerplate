<?php

namespace Modules\Loyalty\Http\Requests;

use App\Http\Requests\DataTableRequest;
use Modules\Loyalty\Enums\EarnedPointStatus;
use Modules\Loyalty\Enums\PointTransactionType;
use Modules\Loyalty\Support\ActivityPeriod;

class EarnedPointsRequest extends DataTableRequest
{
    /** Only these create points (a lot): awards and positive manual adjustments. */
    public const SOURCES = [PointTransactionType::Earn, PointTransactionType::Adjust];

    public function authorize(): bool
    {
        return $this->user()?->can('view-point') ?? false;
    }

    protected function filterRules(): array
    {
        return [
            'period' => ['nullable', 'in:'.implode(',', array_keys(ActivityPeriod::OPTIONS))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'source' => ['nullable', 'in:'.implode(',', array_map(fn ($type) => $type->value, self::SOURCES))],
            'status' => ['nullable', 'in:'.implode(',', array_column(EarnedPointStatus::cases(), 'value'))],
            'expiring' => ['nullable', 'in:30'],
        ];
    }

    public function period(): ActivityPeriod
    {
        return ActivityPeriod::from($this->validated('period') ?? 'month', $this->validated('from'), $this->validated('to'));
    }

    public function source(): ?PointTransactionType
    {
        return PointTransactionType::tryFrom((string) $this->validated('source'));
    }

    public function status(): ?EarnedPointStatus
    {
        return EarnedPointStatus::tryFrom((string) $this->validated('status'));
    }

    public function expiringWithinDays(): ?int
    {
        return $this->validated('expiring') ? (int) $this->validated('expiring') : null;
    }
}
