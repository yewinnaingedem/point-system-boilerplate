<?php

namespace Modules\Loyalty\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Customer\Models\Customer;

class AdjustPointsRequest extends FormRequest
{
    private const MAX_POINTS = 100000000;

    private ?Customer $customer = null;

    public function authorize(): bool
    {
        return $this->user()?->can('adjust-point') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Picked in the customer search (customer::partials.select), so it is always one exact customer.
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')],
            'points' => ['required', 'integer', 'not_in:0', 'between:-'.self::MAX_POINTS.','.self::MAX_POINTS],
            'note' => ['required', 'string', 'max:255'],
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

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['note' => __('reason'), 'customer_id' => __('customer')];
    }
}
