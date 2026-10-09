<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Points → Earned Points lists every customer's credits by date (period filter + newest first). */
    public function up(): void
    {
        Schema::table('loyalty_point_lots', function (Blueprint $table) {
            $table->index('earned_at');
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_point_lots', function (Blueprint $table) {
            $table->dropIndex(['earned_at']);
        });
    }
};
