<?php

namespace Modules\GiftCard\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create or edit a claim: which merchant (our staff only), up to which day, a note. */
class ClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route permission + LimitToOwnMerchant
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->route('claim') === null;

        return [
            // A merchant's own staff always claim for their own merchant.
            'merchant_id' => [$creating && ! $this->user()->isMerchantUser() ? 'required' : 'prohibited', 'integer', Rule::exists('merchants', 'id')],
            'up_to' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function merchantId(): int
    {
        return $this->user()->merchant_id ?? (int) $this->validated('merchant_id');
    }

    public function upTo(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('up_to'));
    }
}
