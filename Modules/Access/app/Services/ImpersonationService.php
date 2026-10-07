<?php

namespace Modules\Access\Services;

use App\Models\User;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * "Login as": an administrator browses the admin as another user to see exactly what they see,
 * then returns to their own account.
 *
 * The original account's id stays in the session for the whole visit, so switching from one
 * user to another still returns to the real administrator, and every permission check for
 * starting is made against that real administrator, never against the borrowed account.
 * Start and stop are written to the log.
 */
final class ImpersonationService
{
    public const SESSION_KEY = 'impersonator_id';

    private const SESSION_NAME_KEY = 'impersonator_name';

    public function __construct(
        private readonly StatefulGuard $guard,
        private readonly Gate $gate,
    ) {}

    public function isImpersonating(Session $session): bool
    {
        return $session->has(self::SESSION_KEY);
    }

    public function impersonatorName(Session $session): ?string
    {
        return $session->get(self::SESSION_NAME_KEY);
    }

    /**
     * @throws ValidationException when the target can't be used
     */
    public function start(Session $session, User $current, User $target): void
    {
        $original = $this->original($session) ?? $current;

        if ($target->is($current) || $target->is($original)) {
            throw ValidationException::withMessages(['user' => __('You are already signed in as :name.', ['name' => $target->name])]);
        }
        if (! $target->is_active) {
            throw ValidationException::withMessages(['user' => __('An inactive user cannot be used to sign in.')]);
        }

        $this->gate->forUser($original)->authorize('impersonate', $target);

        // Set before login(): the Login listener sees it and doesn't touch the target's last_login_at.
        $session->put(self::SESSION_KEY, $original->id);
        $session->put(self::SESSION_NAME_KEY, $original->name);
        $this->guard->login($target);

        Log::notice('Impersonation started', ['by' => $original->id, 'by_email' => $original->email, 'as' => $target->id, 'as_email' => $target->email]);
    }

    /**
     * Back to the real account. Returns it, or null when it no longer exists or was deactivated
     * (then the session is simply signed out).
     */
    public function stop(Session $session, User $current): ?User
    {
        $original = $this->original($session);

        if ($original === null || ! $original->is_active) {
            $this->forget($session);
            $this->guard->logout();

            return null;
        }

        $this->guard->login($original);
        $this->forget($session);

        Log::notice('Impersonation ended', ['by' => $original->id, 'by_email' => $original->email, 'as' => $current->id, 'as_email' => $current->email]);

        return $original;
    }

    private function original(Session $session): ?User
    {
        $id = $session->get(self::SESSION_KEY);

        return $id ? User::find($id) : null;
    }

    private function forget(Session $session): void
    {
        $session->forget([self::SESSION_KEY, self::SESSION_NAME_KEY]);
    }
}
