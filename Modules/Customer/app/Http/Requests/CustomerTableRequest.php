<?php

namespace Modules\Customer\Http\Requests;

use App\Http\Requests\DataTableRequest;
use Modules\Loyalty\Enums\TierLevel;

class CustomerTableRequest extends DataTableRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-customer') ?? false;
    }

    protected function filterRules(): array
    {
        return [
            'status' => ['nullable', 'in:active,inactive'],
            'tier' => ['nullable', 'in:'.implode(',', array_column(TierLevel::cases(), 'value'))],
        ];
    }
}
