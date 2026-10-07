<?php

namespace Modules\Api\Services;

use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Validation\ValidationException;

/**
 * Checks API sign-in credentials. Staff sign in with their email or phone number.
 * Every failure gives the same message, so the API never reveals which accounts exist.
 */
final class ApiAuthenticator
{
    public function __construct(private readonly Hasher $hasher) {}

    /**
     * @throws ValidationException
     */
    public function authenticate(string $login, string $password): User
    {
        $user = User::query()
            ->where(str_contains($login, '@') ? 'email' : 'phone', $login)
            ->first();

        if ($user === null) {
            // Hash anyway so an unknown login takes as long as a wrong password.
            $this->hasher->make($password);
        }

        if ($user === null || ! $user->is_active || ! $this->hasher->check($password, $user->password)) {
            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }

        return $user;
    }
}
