<?php

namespace Modules\Access\Services;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Creates, updates and removes staff accounts, and keeps at least one active
 * administrator in the system at all times.
 */
final class UserService
{
    private const AVATAR_DIRECTORY = 'avatars';

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Filesystem $disk,
        private readonly UserSessionService $sessions,
    ) {}

    /**
     * Roles the acting user may hand out. Only an administrator can create another one.
     *
     * @return Collection<int, Role>
     */
    public function assignableRoles(User $actor): Collection
    {
        return Role::query()
            ->when(! $actor->isAdministrator(), fn ($q) => $q->where('name', '!=', SystemRole::Administrator->value))
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{name: string, email: string, phone?: ?string, password: string, is_active?: bool, roles: list<string>}  $data
     */
    public function create(array $data, ?UploadedFile $avatar = null): User
    {
        return $this->db->transaction(function () use ($data, $avatar) {
            $user = User::create([
                ...$this->attributes($data),
                'password' => $data['password'],
                'avatar' => $avatar ? $this->disk->putFile(self::AVATAR_DIRECTORY, $avatar) : null,
            ]);
            $user->syncRoles($data['roles']);

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, phone?: ?string, password?: ?string, is_active?: bool, roles: list<string>}  $data
     */
    public function update(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        $this->db->transaction(function () use ($user, $data, $avatar) {
            $attributes = $this->attributes($data);

            $passwordChanged = ! empty($data['password']);
            if ($passwordChanged) {
                $attributes['password'] = $data['password'];
            }
            if ($avatar !== null) {
                $attributes['avatar'] = $this->disk->putFile(self::AVATAR_DIRECTORY, $avatar);
            }

            $oldAvatar = $user->avatar;
            $user->update($attributes);
            $user->syncRoles($data['roles']);
            $this->guardLastAdministrator();

            // A new password or deactivation signs the user out everywhere.
            if ($passwordChanged || ! $user->is_active) {
                $this->signOutEverywhere($user);
            }

            if ($avatar !== null && $oldAvatar) {
                $this->disk->delete($oldAvatar);
            }
        });

        return $user;
    }

    public function toggleStatus(User $user, User $actor): User
    {
        $this->guardSelf($user, $actor, __('You cannot deactivate your own account.'));

        $this->db->transaction(function () use ($user) {
            $user->update(['is_active' => ! $user->is_active]);
            $this->guardLastAdministrator();

            if (! $user->is_active) {
                $this->signOutEverywhere($user);
            }
        });

        return $user;
    }

    /**
     * An administrator sets a new password; the user is signed out of every browser and app.
     */
    public function changePassword(User $user, string $password): void
    {
        $this->db->transaction(function () use ($user, $password) {
            $user->update(['password' => $password]);
            $this->signOutEverywhere($user);
        });
    }

    /**
     * Soft delete: the user can't sign in and moves to "Deleted users". Roles and avatar are
     * kept so a restore brings the account back as it was.
     */
    public function delete(User $user, User $actor): void
    {
        $this->guardSelf($user, $actor, __('You cannot delete your own account.'));

        $this->db->transaction(function () use ($user) {
            $this->signOutEverywhere($user);
            $user->delete();
            $this->guardLastAdministrator();
        });
    }

    public function restore(User $user): void
    {
        $user->restore();
    }

    /**
     * Removes a deleted user for good, with their roles (detached by HasRoles) and avatar.
     */
    public function forceDelete(User $user): void
    {
        $avatar = $user->avatar;

        $this->db->transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->forceDelete();
        });

        if ($avatar) {
            $this->disk->delete($avatar);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'merchant_id' => $data['merchant_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    private function signOutEverywhere(User $user): void
    {
        $user->tokens()->delete();
        $this->sessions->clear($user);
    }

    private function guardSelf(User $user, User $actor, string $message): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['user' => $message]);
        }
    }

    /**
     * Runs inside the write transaction, so a failing check rolls the change back.
     */
    private function guardLastAdministrator(): void
    {
        $activeAdmins = User::role(SystemRole::Administrator->value)->active()->count();

        if ($activeAdmins === 0) {
            throw ValidationException::withMessages([
                'user' => __('At least one active administrator must remain.'),
            ]);
        }
    }
}
