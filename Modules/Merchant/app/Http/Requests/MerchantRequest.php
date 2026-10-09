<?php

namespace Modules\Merchant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MerchantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->route('merchant') ? 'edit-merchant' : 'create-merchant') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->user()->isMerchantUser()) {
            // A merchant's own staff keep their contact details up to date; what we pay them,
            // whether they are active and our notes stay with our staff.
            return array_intersect_key($this->allRules(), array_flip(self::MERCHANT_EDITABLE));
        }

        return $this->allRules();
    }

    /** @var list<string> */
    public const MERCHANT_EDITABLE = ['name', 'contact_person', 'phone', 'email', 'address'];

    /**
     * @return array<string, mixed>
     */
    private function allRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'settlement_rate' => ['required', 'numeric', 'min:0', 'max:99999999999', 'decimal:0,4'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['settlement_rate' => __('payout per point')];
    }
}
