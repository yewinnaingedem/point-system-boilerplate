<?php

namespace Modules\Access\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A user's browser sessions. Only possible with SESSION_DRIVER=database, where every
 * session row carries the signed-in user's id.
 */
final class UserSessionService
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly string $driver,
        private readonly string $table,
        private readonly ?string $currentSessionId = null,
    ) {}

    public function supported(): bool
    {
        return $this->driver === 'database';
    }

    /**
     * @return Collection<int, object{id: string, ip_address: ?string, user_agent: ?string, last_activity: CarbonImmutable, is_current: bool}>
     */
    public function forUser(User $user): Collection
    {
        if (! $this->supported()) {
            return collect();
        }

        return $this->db->table($this->table)
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $row) => (object) [
                'id' => $row->id,
                'ip_address' => $row->ip_address,
                'user_agent' => $row->user_agent,
                'last_activity' => CarbonImmutable::createFromTimestamp($row->last_activity, config('app.timezone')),
                'is_current' => $row->id === $this->currentSessionId,
            ]);
    }

    /**
     * Ends every browser session of the user and invalidates their "remember me" cookie, so
     * they must sign in again everywhere. The browser making this request is kept, so an
     * administrator changing their own password isn't thrown out mid-request.
     */
    public function clear(User $user): int
    {
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        if (! $this->supported()) {
            return 0;
        }

        return $this->db->table($this->table)
            ->where('user_id', $user->id)
            ->when($this->currentSessionId, fn ($q, string $id) => $q->where('id', '!=', $id))
            ->delete();
    }
}
