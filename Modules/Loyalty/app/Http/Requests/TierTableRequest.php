<?php

namespace Modules\Loyalty\Http\Requests;

use App\Http\Requests\DataTableRequest;

class TierTableRequest extends DataTableRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-loyaltytier') ?? false;
    }
}
