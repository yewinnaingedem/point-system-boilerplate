<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Starting badge colour per tier; changed on the Loyalty Tiers screen. */
    private const COLORS = [
        'silver' => '#8e9aa6',
        'gold' => '#d4a017',
        'platinum' => '#4a9d9c',
        'diamond' => '#3c8dbc',
    ];

    private const FALLBACK = '#6c757d';

    public function up(): void
    {
        Schema::table('loyalty_tiers', function (Blueprint $table) {
            // "#rrggbb", validated on save; printed into a style attribute for the tier badge.
            $table->string('color', 7)->default(self::FALLBACK)->after('guarantee_months');
        });

        foreach (self::COLORS as $level => $color) {
            DB::table('loyalty_tiers')->where('tier_level', $level)->update(['color' => $color]);
        }
    }

    public function down(): void
    {
        Schema::table('loyalty_tiers', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
