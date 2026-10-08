<?php

namespace Modules\Merchant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReverseRedemptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reverse-redemption') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:255']];
    }
}
