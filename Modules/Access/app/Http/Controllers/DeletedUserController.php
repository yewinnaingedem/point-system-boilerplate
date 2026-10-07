<?php

namespace Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Modules\Access\Services\UserService;

/**
 * Users in "Deleted users" ({deletedUser} binds soft-deleted rows only).
 */
class DeletedUserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function restore(User $deletedUser): RedirectResponse
    {
        Gate::authorize('restore', $deletedUser);

        $this->users->restore($deletedUser);

        return redirect()->route('admin.access.users.show', $deletedUser)
            ->with('success', __('User :name restored.', ['name' => $deletedUser->name]));
    }

    public function destroy(User $deletedUser): RedirectResponse
    {
        Gate::authorize('forceDelete', $deletedUser);

        $this->users->forceDelete($deletedUser);

        return redirect()->route('admin.access.users.index', ['status' => 'deleted'])
            ->with('success', __('User :name permanently deleted.', ['name' => $deletedUser->name]));
    }
}
