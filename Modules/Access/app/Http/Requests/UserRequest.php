<?php

namespace Modules\Access\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Access\Services\UserService;

/**
 * Create and update a staff account. The password is required only when creating.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->editedUser();

        return $user === null
            ? $this->user()->can('create-user')
            : $this->user()->can('update', $user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->editedUser();
        $assignable = app(UserService::class)->assignableRoles($this->user())->pluck('name')->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'is_active' => ['boolean'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in($assignable)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    private function editedUser(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }
}
