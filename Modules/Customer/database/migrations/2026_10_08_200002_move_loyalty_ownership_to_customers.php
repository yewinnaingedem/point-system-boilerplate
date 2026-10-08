<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tiers, points and redemptions belong to customers, not staff users: user_id becomes
 * customer_id on every loyalty / merchant table. Existing rows point at users and can't be
 * mapped, so the migration refuses to run while any of these tables holds data.
 */
return new class extends Migration
{
    /** table => what happens to its rows when the customer is deleted */
    private const TABLES = [
        'loyalty_member_statuses' => 'cascade',
        'loyalty_cycle_spendings' => 'cascade',
        'loyalty_tier_events' => 'cascade',
        'loyalty_point_accounts' => 'cascade',
        'loyalty_point_transactions' => 'cascade',
        'merchant_redemptions' => 'null', // what is owed to the merchant stays on record
    ];

    public function up(): void
    {
        $this->assertEmpty();
        $this->swap('user_id', 'customer_id', 'customers');

        // Spending at merchants is now done by customers through their own tokens.
        DB::table(config('permission.table_names.permissions', 'permissions'))->where('name', 'redeem-reward')->delete();
    }

    public function down(): void
    {
        $this->assertEmpty();
        $this->swap('customer_id', 'user_id', 'users');
    }

    private function swap(string $from, string $to, string $references): void
    {
        foreach (self::TABLES as $table => $onDelete) {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign([$from]));
            Schema::table($table, fn (Blueprint $t) => $t->renameColumn($from, $to));
            Schema::table($table, function (Blueprint $t) use ($to, $references, $onDelete) {
                $foreign = $t->foreign($to)->references('id')->on($references);
                $onDelete === 'cascade' ? $foreign->cascadeOnDelete() : $foreign->nullOnDelete();
            });
        }
    }

    private function assertEmpty(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException("{$table} has rows that belong to users. Remove them first (php artisan merchant:demo-data --remove), then migrate again.");
            }
        }
    }
};
