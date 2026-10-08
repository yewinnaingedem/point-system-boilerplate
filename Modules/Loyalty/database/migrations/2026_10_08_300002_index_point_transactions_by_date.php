<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Points Activity filters every customer's movements by date (and type): index for that. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_point_transactions', function (Blueprint $table) {
            $table->index('created_at');
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_point_transactions', function (Blueprint $table) {
            $table->dropIndex(['type', 'created_at']);
            $table->dropIndex(['created_at']);
        });
    }
};
