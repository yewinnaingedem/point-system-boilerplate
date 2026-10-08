<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A partner where members spend points (e.g. KFC). Merchants never award points.
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('contact_person', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            // What we pay the merchant per point redeemed there, unless a reward sets its own payout.
            $table->decimal('settlement_rate', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('is_active');
        });

        // A merchant's shop (e.g. KFC Junction Square). Its 6-digit code is typed in by the
        // shop's staff to confirm a redemption there.
        Schema::create('merchant_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->text('code'); // encrypted (APP_KEY); compared in constant time
            $table->dateTime('code_changed_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['merchant_id', 'name']);
        });

        // What a member can get at a merchant, and its price in points.
        Schema::create('merchant_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unsignedInteger('points_cost');
            // Paid to the merchant per redemption; null = points_cost x merchant settlement_rate.
            $table->decimal('payout_amount', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['merchant_id', 'name']);
        });

        // One reward handed over at one branch. Amounts are snapshots, so later price or
        // rate changes never alter what is owed. settlement_id is filled by the payout module.
        Schema::create('merchant_redemptions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('merchant_branches')->restrictOnDelete();
            $table->foreignId('reward_id')->nullable()->constrained('merchant_rewards')->nullOnDelete();
            $table->string('reward_name', 150);
            $table->unsignedInteger('points');
            $table->decimal('payout_amount', 15, 2);
            $table->string('status', 20);
            $table->unsignedBigInteger('settlement_id')->nullable();
            $table->string('request_id', 64)->nullable(); // app's idempotency key
            $table->dateTime('redeemed_at');
            $table->dateTime('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reversal_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'request_id']);
            $table->index(['merchant_id', 'status', 'settlement_id']);
            $table->index('redeemed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_redemptions');
        Schema::dropIfExists('merchant_rewards');
        Schema::dropIfExists('merchant_branches');
        Schema::dropIfExists('merchants');
    }
};
