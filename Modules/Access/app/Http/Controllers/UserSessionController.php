<?php

namespace Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Modules\Access\Services\UserSessionService;

class UserSessionController extends Controller
{
    public function destroy(User $user, UserSessionService $sessions): RedirectResponse
    {
        Gate::authorize('clearSessions', $user);

        $count = $sessions->clear($user);

        return back()->with('success', __(':name was signed out of :count browser sessions.', ['name' => $user->name, 'count' => $count]));
    }
}
