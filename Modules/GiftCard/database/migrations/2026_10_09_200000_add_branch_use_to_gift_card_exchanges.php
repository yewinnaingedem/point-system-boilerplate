<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An issued gift card is completed ("used") at a merchant branch: shop staff confirm with the
     * branch's 6-digit code. The shop handed over goods worth the card's value, so that is owed to
     * the merchant (payout_amount, a snapshot); settlement_id is for the future settlement module.
     */
    public function up(): void
    {
        Schema::table('gift_card_exchanges', function (Blueprint $table) {
            $table->dateTime('used_at')->nullable()->after('expires_at');
            $table->foreignId('merchant_id')->nullable()->after('used_at')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->after('merchant_id')->constrained('merchant_branches')->restrictOnDelete();
            $table->decimal('payout_amount', 15, 2)->nullable()->after('branch_id');
            $table->unsignedBigInteger('settlement_id')->nullable()->after('payout_amount');

            $table->index(['merchant_id', 'status', 'settlement_id']); // what is owed to a merchant
        });
    }

    public function down(): void
    {
        Schema::table('gift_card_exchanges', function (Blueprint $table) {
            $table->dropIndex(['merchant_id', 'status', 'settlement_id']);
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('merchant_id');
            $table->dropColumn(['used_at', 'payout_amount', 'settlement_id']);
        });
    }
};
