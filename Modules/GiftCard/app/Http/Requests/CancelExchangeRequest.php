<?php

namespace Modules\GiftCard\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelExchangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cancel-giftcardexchange') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:255']];
    }
}
