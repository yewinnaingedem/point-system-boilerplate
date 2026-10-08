<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Points that expire: every credit becomes a lot with its own expiry date; spending takes from
 * the lots that expire first. Plus a per-customer monthly summary kept in step with the ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_point_transactions', function (Blueprint $table) {
            // Caller's idempotency key (e.g. the partner's order number): one award per reference.
            $table->string('reference', 100)->nullable()->unique()->after('note');
        });

        // One lot per credit (earn, positive adjustment). remaining goes down as points are
        // spent or expire. expires_at is exclusive: the lot is dead from that moment on.
        Schema::create('loyalty_point_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('loyalty_point_transactions')->cascadeOnDelete();
            $table->unsignedBigInteger('points');
            $table->unsignedBigInteger('remaining');
            $table->dateTime('earned_at');
            $table->dateTime('expires_at')->nullable(); // null = never expires
            $table->timestamps();

            $table->index(['customer_id', 'remaining', 'expires_at']); // spend order + expiry sweep per customer
            $table->index(['expires_at', 'remaining']);                // daily expiry sweep
        });

        // Which lots a debit (redeem, negative adjustment, expiry) took from, so a reversal can
        // put the points back where they came from, with their original expiry.
        Schema::create('loyalty_point_lot_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('loyalty_point_transactions')->cascadeOnDelete();
            $table->foreignId('lot_id')->constrained('loyalty_point_lots')->cascadeOnDelete();
            $table->unsignedBigInteger('points');
            $table->timestamps();

            $table->index('lot_id');
        });

        // Per customer per month, updated in the same transaction as every ledger row: answers
        // "what happened to points in March" without summing the ledger.
        Schema::create('loyalty_point_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('period'); // first day of the month
            foreach (['earned', 'redeemed', 'reversed', 'adjusted_in', 'adjusted_out', 'expired'] as $column) {
                $table->unsignedBigInteger($column)->default(0);
            }
            $table->timestamps();

            $table->unique(['customer_id', 'period']);
            $table->index('period');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_point_summaries');
        Schema::dropIfExists('loyalty_point_lot_usages');
        Schema::dropIfExists('loyalty_point_lots');
        Schema::table('loyalty_point_transactions', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn('reference');
        });
    }

    /**
     * Balances from before lots existed become one never-expiring lot each (we can't know which
     * credits they are left over from); the summary is rebuilt from the whole ledger.
     */
    private function backfill(): void
    {
        $now = now();

        foreach (DB::table('loyalty_point_accounts')->where('balance', '>', 0)->get() as $account) {
            $last = DB::table('loyalty_point_transactions')->where('customer_id', $account->customer_id)->orderByDesc('id')->value('id');
            if ($last === null) {
                continue;
            }
            DB::table('loyalty_point_lots')->insert([
                'customer_id' => $account->customer_id, 'transaction_id' => $last,
                'points' => $account->balance, 'remaining' => $account->balance,
                'earned_at' => $now, 'expires_at' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $columns = ['earn' => 'earned', 'redeem' => 'redeemed', 'reversal' => 'reversed', 'expire' => 'expired'];
        $rows = [];
        foreach (DB::table('loyalty_point_transactions')->orderBy('id')->cursor() as $tx) {
            $period = substr((string) $tx->created_at, 0, 7).'-01';
            $key = "{$tx->customer_id}|{$period}";
            $rows[$key] ??= ['customer_id' => $tx->customer_id, 'period' => $period, 'earned' => 0, 'redeemed' => 0,
                'reversed' => 0, 'adjusted_in' => 0, 'adjusted_out' => 0, 'expired' => 0, 'created_at' => $now, 'updated_at' => $now];
            $column = $tx->type === 'adjust' ? ($tx->points >= 0 ? 'adjusted_in' : 'adjusted_out') : ($columns[$tx->type] ?? null);
            if ($column !== null) {
                $rows[$key][$column] += abs((int) $tx->points);
            }
        }
        foreach (array_chunk(array_values($rows), 500) as $chunk) {
            DB::table('loyalty_point_summaries')->insert($chunk);
        }
    }
};
