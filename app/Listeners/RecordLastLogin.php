<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Modules\Access\Services\ImpersonationService;

class RecordLastLogin
{
    public function handle(Login $event): void
    {
        // "Login as" (and returning from it) is not the user signing in themselves.
        if (request()->hasSession() && request()->session()->has(ImpersonationService::SESSION_KEY)) {
            return;
        }

        if ($event->user instanceof User) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        }
    }
}
