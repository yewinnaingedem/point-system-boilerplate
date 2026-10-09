<?php

namespace Modules\GiftCard\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Customer\Models\Customer;

/** Staff exchange a gift card for a customer, picked in the customer search (exact id). */
class ExchangeForCustomerRequest extends FormRequest
{
    private ?Customer $customer = null;

    public function authorize(): bool
    {
        return $this->user()?->can('create-giftcardexchange') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')],
            'gift_card_id' => ['required', 'integer', Rule::exists('gift_cards', 'id')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $validator->errors()->has('customer_id') && ! $this->customer()->is_active) {
                $validator->errors()->add('customer_id', __('This customer is deactivated.'));
            }
        }];
    }

    public function customer(): Customer
    {
        return $this->customer ??= Customer::query()->findOrFail((int) $this->input('customer_id'));
    }
}
