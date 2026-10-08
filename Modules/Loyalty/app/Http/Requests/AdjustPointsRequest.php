<?php

namespace Modules\Loyalty\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Customer\Models\Customer;

class AdjustPointsRequest extends FormRequest
{
    private const MAX_POINTS = 100000000;

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
            'customer' => ['required', 'string', 'max:150'],
            'points' => ['required', 'integer', 'not_in:0', 'between:-'.self::MAX_POINTS.','.self::MAX_POINTS],
            'note' => ['required', 'string', 'max:255'],
        ];
    }

    /** The customer is entered by email or phone. */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $validator->errors()->has('customer') && $this->customer() === null) {
                $validator->errors()->add('customer', __('No customer has this email or phone.'));
            }
        }];
    }

    public function customer(): ?Customer
    {
        $login = trim((string) $this->input('customer'));

        return $login === '' ? null : Customer::query()->where('email', $login)->orWhere('phone', $login)->first();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['note' => __('reason')];
    }
}
