<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One payment to a merchant for gift cards used at its branches. The cards it covers point
     * at it through gift_card_exchanges.settlement_id; until then they are the merchant's claim.
     */
    public function up(): void
    {
        Schema::create('gift_card_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();               // STL-20261009-K7Q2XM
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('cards_count');
            $table->decimal('amount', 15, 2);
            $table->date('period_from')->nullable();                  // used_at range the statement covered
            $table->date('period_to')->nullable();
            $table->date('paid_on');
            $table->string('payment_reference', 100)->nullable();     // e.g. bank transfer number
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['merchant_id', 'paid_on']);
        });

        Schema::table('gift_card_exchanges', function (Blueprint $table) {
            $table->foreign('settlement_id')->references('id')->on('gift_card_settlements')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gift_card_exchanges', function (Blueprint $table) {
            $table->dropForeign(['settlement_id']);
        });
        Schema::dropIfExists('gift_card_settlements');
    }
};
