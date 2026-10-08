<?php

namespace Modules\Merchant\Http\Requests;

use App\Http\Requests\DataTableRequest;

/** Merchants, branches and rewards lists. */
class MerchantTableRequest extends DataTableRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-merchant') ?? false;
    }

    protected function filterRules(): array
    {
        return ['status' => ['nullable', 'in:active,inactive']];
    }
}
