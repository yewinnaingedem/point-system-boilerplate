<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Access\Database\Seeders\AdminUserSeeder;
use Modules\Access\Database\Seeders\RoleSeeder;
use Nwidart\Modules\Facades\Module;

class DatabaseSeeder extends Seeder
{
    /**
     * Every enabled module registers its permissions first, then the system roles and
     * the first administrator are created. Safe to re-run.
     */
    public function run(): void
    {
        $this->call($this->modulePermissionSeeders());
        $this->call([RoleSeeder::class, AdminUserSeeder::class]);
    }

    /**
     * @return list<class-string<Seeder>>
     */
    private function modulePermissionSeeders(): array
    {
        return collect(Module::allEnabled())
            ->map(fn ($module) => "Modules\\{$module->getName()}\\Database\\Seeders\\{$module->getName()}DatabaseSeeder")
            ->filter(fn (string $class) => class_exists($class))
            ->values()
            ->all();
    }
}
