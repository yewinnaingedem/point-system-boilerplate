<?php

namespace Modules\Loyalty\Http\Requests;

use App\Http\Requests\DataTableRequest;

class PointTableRequest extends DataTableRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-point') ?? false;
    }
}
