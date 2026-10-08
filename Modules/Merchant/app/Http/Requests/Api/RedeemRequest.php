<?php

namespace Modules\Merchant\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Customer\Models\Customer;

class RedeemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Customer;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer'],
            'reward_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'digits:6'],
            // Optional idempotency key: send the same value when retrying after a timeout.
            'request_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}
