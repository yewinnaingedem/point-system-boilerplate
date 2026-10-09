<?php

namespace Modules\GiftCard\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Our staff pay or reject a claim. */
class DecideClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->isMerchantUser(); // also closed in LimitToOwnMerchant
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->routeIs('admin.claims.pay')
            ? ['paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'payment_reference' => ['nullable', 'string', 'max:100']]
            : ['reject_reason' => ['required', 'string', 'max:255']];
    }
}
