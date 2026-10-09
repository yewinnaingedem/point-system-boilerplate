<?php

namespace Modules\Api\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApiClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('client') ? 'edit-apiclient' : 'create-apiclient');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }
}
