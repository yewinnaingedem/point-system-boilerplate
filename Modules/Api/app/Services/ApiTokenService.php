<?php

namespace Modules\Api\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Issues and revokes API tokens. Tokens are Sanctum personal access tokens: stored hashed in
 * `personal_access_tokens`, so revoking one (delete the row) takes effect on the next request.
 * The token name is the device name the app sent.
 */
final class ApiTokenService
{
    private const ABILITIES = ['*'];

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly int $ttlDays,
        private readonly int $maxDevices,
    ) {}

    /**
     * Signing in again on the same device replaces that device's token; a new device beyond
     * the limit pushes out the least recently used one.
     */
    public function issue(User $user, string $deviceName): NewAccessToken
    {
        return $this->db->transaction(function () use ($user, $deviceName) {
            $user->tokens()->where('name', $deviceName)->delete();
            $this->keepNewest($user, $this->maxDevices - 1);

            return $user->createToken($deviceName, self::ABILITIES, now()->addDays($this->ttlDays));
        });
    }

    /**
     * New token for the same device; the one used for this request stops working.
     */
    public function refresh(User $user, PersonalAccessToken $current): NewAccessToken
    {
        return $this->db->transaction(function () use ($user, $current) {
            $current->delete();

            return $user->createToken($current->name, self::ABILITIES, now()->addDays($this->ttlDays));
        });
    }

    public function revoke(PersonalAccessToken $token): void
    {
        $token->delete();
    }

    public function revokeAll(User $user): int
    {
        return $user->tokens()->delete();
    }

    private function keepNewest(User $user, int $keep): void
    {
        $user->tokens()
            ->orderByRaw('coalesce(last_used_at, created_at) desc')
            ->orderByDesc('id') // two sign-ins in the same second: the later one is newer
            ->get()
            ->slice(max(0, $keep))
            ->each(fn (PersonalAccessToken $token) => $token->delete());
    }
}
