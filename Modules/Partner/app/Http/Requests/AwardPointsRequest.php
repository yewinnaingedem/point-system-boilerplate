<?php

namespace Modules\Partner\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AwardPointsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // AuthenticatePartner already checked the partner key
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer' => ['required', 'array'],
            'customer.external_id' => ['required', 'string', 'max:100'],
            'customer.name' => ['required', 'string', 'max:150'],
            'customer.email' => ['nullable', 'email', 'max:150'],
            'customer.phone' => ['nullable', 'string', 'max:50'],
            'points' => ['required', 'integer', 'min:1', 'max:'.config('partner.max_points_per_award')],
            'reference' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
            'spent_amount' => ['nullable', 'numeric', 'gt:0', 'max:9999999999999', 'decimal:0,2'],
        ];
    }
}
