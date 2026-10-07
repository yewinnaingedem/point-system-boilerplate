<?php

namespace Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Access\Http\Requests\ChangePasswordRequest;
use Modules\Access\Services\UserService;

class UserPasswordController extends Controller
{
    public function edit(User $user): View
    {
        Gate::authorize('changePassword', $user);

        return view('access::users.password', ['user' => $user]);
    }

    public function update(ChangePasswordRequest $request, User $user, UserService $users): RedirectResponse
    {
        $users->changePassword($user, $request->validated('password'));

        return redirect()->route('admin.access.users.show', $user)
            ->with('success', __('Password changed. :name has been signed out of every browser and app.', ['name' => $user->name]));
    }
}
