<?php

namespace Modules\Loyalty\Http\Requests;

use App\Http\Requests\DataTableRequest;
use Modules\Loyalty\Enums\PointTransactionType;
use Modules\Loyalty\Support\ActivityPeriod;

class PointActivityRequest extends DataTableRequest
{
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
            'type' => ['nullable', 'in:'.implode(',', array_column(PointTransactionType::cases(), 'value'))],
        ];
    }

    public function period(): ActivityPeriod
    {
        return ActivityPeriod::from($this->validated('period'), $this->validated('from'), $this->validated('to'));
    }

    public function type(): ?PointTransactionType
    {
        return PointTransactionType::tryFrom((string) $this->validated('type'));
    }
}
