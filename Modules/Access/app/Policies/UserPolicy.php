<?php

namespace Modules\Access\Policies;

use App\Models\User;

/**
 * Route middleware already checks the edit-user / delete-user / impersonate-user permission.
 * This adds the rule a permission can't express: only an administrator may change, delete or
 * sign in as an administrator.
 */
class UserPolicy
{
    public function update(User $actor, User $user): bool
    {
        return $actor->can('edit-user') && $this->mayManage($actor, $user);
    }

    public function delete(User $actor, User $user): bool
    {
        return $actor->can('delete-user') && $this->mayManage($actor, $user);
    }

    public function changePassword(User $actor, User $user): bool
    {
        return $this->update($actor, $user);
    }

    /**
     * On your own account this signs out your other browsers; the current one is always kept.
     */
    public function clearSessions(User $actor, User $user): bool
    {
        return $this->update($actor, $user);
    }

    /**
     * "Login as". Checked against the real administrator, never the borrowed account.
     */
    public function impersonate(User $actor, User $user): bool
    {
        return $actor->can('impersonate-user') && $user->is_active && ! $user->is($actor) && $this->mayManage($actor, $user);
    }

    public function restore(User $actor, User $user): bool
    {
        return $this->delete($actor, $user);
    }

    public function forceDelete(User $actor, User $user): bool
    {
        return $this->delete($actor, $user);
    }

    private function mayManage(User $actor, User $user): bool
    {
        return ! $user->isAdministrator() || $actor->isAdministrator();
    }
}
