<?php

namespace Modules\GiftCard\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Loyalty\Enums\TierLevel;

class GiftCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->route('giftCard') ? 'edit-giftcard' : 'create-giftcard') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'points_cost' => ['required', 'integer', 'min:1', 'max:100000000'],
            'face_value' => ['required', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'min_tier' => ['nullable', 'in:'.implode(',', array_column(TierLevel::cases(), 'value'))],
            'stock' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'valid_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'requires_verification' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'requires_verification' => $this->boolean('requires_verification'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['points_cost' => __('points'), 'per_customer_limit' => __('max per customer'), 'min_tier' => __('tier')];
    }
}
