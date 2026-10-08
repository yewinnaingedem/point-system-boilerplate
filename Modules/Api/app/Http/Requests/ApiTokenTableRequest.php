<?php

namespace Modules\Api\Http\Requests;

use App\Http\Requests\DataTableRequest;

class ApiTokenTableRequest extends DataTableRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-apitoken') ?? false;
    }
}
