<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Starting tier configuration (amounts in the store currency). Edited later on the
     * Loyalty Tiers screen; Silver is the entry tier, so its threshold and guarantee stay 0.
     */
    private const DEFAULT_TIERS = [
        ['tier_level' => 'silver', 'spending_threshold' => 0, 'guarantee_months' => 0],
        ['tier_level' => 'gold', 'spending_threshold' => 500000, 'guarantee_months' => 3],
        ['tier_level' => 'platinum', 'spending_threshold' => 1500000, 'guarantee_months' => 3],
        ['tier_level' => 'diamond', 'spending_threshold' => 3000000, 'guarantee_months' => 3],
    ];

    public function up(): void
    {
        // Tier configuration: one row per TierLevel.
        Schema::create('loyalty_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('tier_level', 20)->unique();
            $table->decimal('spending_threshold', 15, 2);
            $table->unsignedSmallInteger('guarantee_months')->default(0);
            $table->timestamps();
        });

        // One row per member: the tier state machine's current state. The row is also the
        // per-member lock (SELECT ... FOR UPDATE) that serialises concurrent transactions.
        Schema::create('loyalty_member_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('current_tier', 20);
            $table->dateTime('guarantee_expires_at')->nullable();
            $table->dateTime('current_cycle_start');
            $table->dateTime('current_cycle_end');
            // min(current_cycle_end, guarantee_expires_at if still ahead): when the scheduled
            // evaluation has to look at this member again.
            $table->dateTime('next_evaluation_at')->index();
            $table->dateTime('last_evaluated_at')->nullable();
            $table->timestamps();

            $table->index('current_tier');
        });

        // Pre-aggregated spending per member per cycle (O(1) read, atomic increments).
        // Its id is the cycle_id; a closed cycle's row is kept as history.
        Schema::create('loyalty_cycle_spendings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('cycle_start');
            $table->dateTime('cycle_end');
            $table->decimal('total_spent', 15, 2)->default(0);
            $table->unsignedInteger('transaction_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'cycle_start']);
        });

        // Audit trail of state machine transitions (enrolment, upgrades, guarantee
        // extensions, guarantee protection at rollover, demotions).
        Schema::create('loyalty_tier_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('transition', 20);
            $table->string('from_tier', 20)->nullable();
            $table->string('to_tier', 20);
            $table->decimal('cycle_spent', 15, 2);
            $table->dateTime('guarantee_expires_at')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
        });

        $now = now();
        DB::table('loyalty_tiers')->insert(array_map(
            fn (array $tier) => [...$tier, 'created_at' => $now, 'updated_at' => $now],
            self::DEFAULT_TIERS,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_tier_events');
        Schema::dropIfExists('loyalty_cycle_spendings');
        Schema::dropIfExists('loyalty_member_statuses');
        Schema::dropIfExists('loyalty_tiers');
    }
};
