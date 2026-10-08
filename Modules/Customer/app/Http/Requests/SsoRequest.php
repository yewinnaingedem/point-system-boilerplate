<?php

namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SsoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the token in the body is the credential
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:4096'],
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }
}
