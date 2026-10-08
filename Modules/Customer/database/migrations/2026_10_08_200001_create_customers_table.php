<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // People who collect and spend points. They are created and kept up to date from the
        // partner Laravel project's sign-in token (see CustomerSsoService); staff are `users`.
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 100)->unique(); // the customer's id in the partner project (JWT "sub")
            $table->string('name', 150);
            $table->string('email', 150)->nullable()->index();
            $table->string('phone', 50)->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_login_at')->nullable();
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
