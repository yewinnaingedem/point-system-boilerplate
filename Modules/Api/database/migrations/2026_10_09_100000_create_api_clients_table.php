<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Systems that call the signed gateway (POST /api/v1/gateway): each has a public `app_id`
     * (sent as biz_content.appid) and a secret key it signs with. The key is stored encrypted,
     * because the server needs it in clear to check the signature.
     */
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('app_id', 64)->unique();
            $table->text('secret');
            $table->boolean('is_active')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamp('secret_rotated_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
