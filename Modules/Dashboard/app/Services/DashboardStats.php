<?php

namespace Modules\Dashboard\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data for the dashboard. Each method is one small query.
 */
final class DashboardStats
{
    private const RECENT_LOGINS = 6;

    /**
     * @return Collection<int, User>
     */
    public function recentLogins(): Collection
    {
        return User::query()
            ->with('roles:id,name')
            ->whereNotNull('last_login_at')
            ->latest('last_login_at')
            ->limit(self::RECENT_LOGINS)
            ->get(['id', 'name', 'email', 'avatar', 'last_login_at']);
    }

    /** The shop a merchant's own staff belong to, for the welcome line. */
    public function merchantName(int $merchantId): ?string
    {
        return DB::table('merchants')->where('id', $merchantId)->value('name');
    }
}
