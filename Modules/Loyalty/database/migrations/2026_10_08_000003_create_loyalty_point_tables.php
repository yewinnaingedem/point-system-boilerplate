<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per member: the current balance. Also the per-member lock (FOR UPDATE)
        // that makes two simultaneous redemptions unable to spend the same points.
        Schema::create('loyalty_point_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('balance')->default(0);
            $table->timestamps();
        });

        // Every change to a balance, never edited afterwards: the audit trail and history.
        Schema::create('loyalty_point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->bigInteger('points'); // + credit, - debit
            $table->unsignedBigInteger('balance_after');
            $table->nullableMorphs('source'); // e.g. the merchant redemption it belongs to
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'id']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_point_transactions');
        Schema::dropIfExists('loyalty_point_accounts');
    }
};
