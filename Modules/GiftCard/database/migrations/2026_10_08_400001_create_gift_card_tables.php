<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gift card types customers can exchange points for.
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unsignedInteger('points_cost');
            $table->decimal('face_value', 15, 2);
            $table->string('min_tier', 20)->nullable();             // TierLevel value; null = every tier
            $table->unsignedInteger('stock')->nullable();           // left to give out; null = unlimited
            $table->unsignedInteger('per_customer_limit')->nullable(); // max exchanges per customer; null = no limit
            $table->boolean('requires_verification')->default(false);  // emailed 6-digit code before points are taken
            $table->unsignedSmallInteger('valid_days')->nullable(); // issued card validity; null = no expiry
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        // One exchange: pending (waiting for the emailed code), issued (points taken, code given),
        // cancelled (by staff: points and stock returned) or failed (too many wrong codes / expired).
        Schema::create('gift_card_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_card_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->unsignedInteger('points');
            $table->decimal('face_value', 15, 2);
            $table->string('code', 30)->nullable()->unique();      // the gift card code, once issued
            $table->string('verification_hash', 64)->nullable();
            $table->dateTime('verification_expires_at')->nullable();
            $table->unsignedTinyInteger('verification_attempts')->default(0);
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['gift_card_id', 'customer_id', 'status']); // per-customer limit check
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_exchanges');
        Schema::dropIfExists('gift_cards');
    }
};
