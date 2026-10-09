<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Settlements become claims: a merchant (or our staff for them) claims its used gift cards,
     * our staff pay or reject the claim. Existing settlements were paid, so they become paid claims.
     */
    public function up(): void
    {
        Schema::table('gift_card_exchanges', function (Blueprint $table) {
            $table->dropForeign(['settlement_id']);
        });
        Schema::rename('gift_card_settlements', 'merchant_claims');
        Schema::table('gift_card_exchanges', function (Blueprint $table) {
            $table->renameColumn('settlement_id', 'claim_id');
        });

        Schema::table('merchant_claims', function (Blueprint $table) {
            $table->string('status', 20)->default('submitted')->after('merchant_id');
            $table->date('up_to')->nullable()->after('amount');                 // cards used up to this day
            $table->string('note', 500)->nullable()->after('up_to');            // from whoever made the claim
            $table->foreignId('decided_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable()->after('decided_by');
            $table->string('reject_reason', 255)->nullable()->after('decided_at');
            $table->index(['status', 'merchant_id']);
        });
        DB::table('merchant_claims')->update([
            'status' => 'paid',
            'up_to' => DB::raw('coalesce(period_to, paid_on)'),
            'note' => DB::raw('notes'),
            'decided_by' => DB::raw('created_by'),
            'decided_at' => DB::raw('created_at'),
        ]);
        Schema::table('merchant_claims', function (Blueprint $table) {
            $table->dropColumn(['period_from', 'period_to', 'notes']);
        });
        Schema::table('merchant_claims', function (Blueprint $table) {
            $table->date('paid_on')->nullable()->change();
        });

        Schema::table('gift_card_exchanges', function (Blueprint $table) {
            $table->foreign('claim_id')->references('id')->on('merchant_claims')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gift_card_exchanges', function (Blueprint $table) {
            $table->dropForeign(['claim_id']);
        });
        DB::table('gift_card_exchanges')->whereIn('claim_id', DB::table('merchant_claims')->where('status', '!=', 'paid')->select('id'))->update(['claim_id' => null]);
        DB::table('merchant_claims')->where('status', '!=', 'paid')->delete();
        Schema::table('merchant_claims', function (Blueprint $table) {
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('notes', 500)->nullable();
        });
        DB::table('merchant_claims')->update(['period_to' => DB::raw('up_to'), 'notes' => DB::raw('note')]);
        Schema::table('merchant_claims', function (Blueprint $table) {
            $table->dropIndex(['status', 'merchant_id']);
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn(['status', 'up_to', 'note', 'decided_at', 'reject_reason']);
        });
        Schema::rename('merchant_claims', 'gift_card_settlements');
        Schema::table('gift_card_exchanges', function (Blueprint $table) {
            $table->renameColumn('claim_id', 'settlement_id');
            $table->foreign('settlement_id')->references('id')->on('gift_card_settlements')->restrictOnDelete();
        });
    }
};
