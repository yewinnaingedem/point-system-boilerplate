<?php

namespace Modules\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Loyalty\Enums\TierLevel;

/**
 * Configuration of one tier: spending needed within a cycle, how long the tier is
 * guaranteed once reached, and the colour of its badge.
 *
 * @property TierLevel $tier_level
 * @property string $spending_threshold
 * @property int $guarantee_months
 * @property string $color "#rrggbb"
 */
class LoyaltyTier extends Model
{
    /** Relative luminance above which dark text reads better than white on the badge. */
    private const LIGHT_BACKGROUND = 0.55;

    protected $fillable = ['tier_level', 'spending_threshold', 'guarantee_months', 'color'];

    protected function casts(): array
    {
        return [
            'tier_level' => TierLevel::class,
            'spending_threshold' => 'decimal:2',
            'guarantee_months' => 'integer',
        ];
    }

    /** Route binding by level: /admin/loyalty/tiers/gold/edit. */
    public function getRouteKeyName(): string
    {
        return 'tier_level';
    }

    /** The cast enum can't go into a URL as is. */
    public function getRouteKey(): string
    {
        return $this->tier_level->value;
    }

    /** Text colour that stays readable on the tier colour. */
    public function textColor(): string
    {
        [$r, $g, $b] = array_map(fn (string $hex) => hexdec($hex) / 255, str_split(ltrim($this->color, '#'), 2));

        return (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) > self::LIGHT_BACKGROUND ? '#1f2328' : '#ffffff';
    }
}
