<?php

namespace Modules\Access\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * Groups "<action>-<resource>" permissions by resource for the role screen.
 */
final class PermissionMatrix
{
    private const ACTION_ORDER = ['view', 'create', 'edit', 'delete'];

    /** Display names for resources whose slug doesn't read well on its own. */
    private const RESOURCE_LABELS = ['appsetting' => 'App Settings'];

    /**
     * @param  Collection<int, Permission>  $permissions
     * @return Collection<string, Collection<int, Permission>> resource => its permissions
     */
    public function group(Collection $permissions): Collection
    {
        return $permissions
            ->groupBy(fn (Permission $permission) => self::resource($permission->name))
            ->sortKeys()
            ->map(fn (Collection $group) => $group->sortBy(fn (Permission $p) => $this->actionRank($p->name))->values());
    }

    public static function resource(string $permission): string
    {
        return Str::after($permission, '-');
    }

    public static function action(string $permission): string
    {
        return Str::before($permission, '-');
    }

    public static function resourceLabel(string $resource): string
    {
        return self::RESOURCE_LABELS[$resource] ?? Str::headline($resource);
    }

    private function actionRank(string $permission): int
    {
        $rank = array_search(self::action($permission), self::ACTION_ORDER, true);

        return $rank === false ? count(self::ACTION_ORDER) : $rank;
    }
}
