<?php

namespace Modules\Loyalty\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Loyalty\Enums\TierLevel;
use Modules\Loyalty\Models\LoyaltyTier;
use Modules\Loyalty\Services\TierConfigService;
use Modules\Loyalty\Support\Amount;

class UpdateTierRequest extends FormRequest
{
    private const MAX_THRESHOLD = 9999999999999.99;

    private const HEX_COLOR = 'regex:/^#[0-9a-fA-F]{6}$/';

    public function authorize(): bool
    {
        return $this->user()?->can('edit-loyaltytier') ?? false;
    }

    /**
     * The base tier only has a colour; the others also a threshold and a guarantee.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['color' => ['required', 'string', self::HEX_COLOR]];

        if (! $this->tier()->tier_level->isBase()) {
            $rules['spending_threshold'] = ['required', 'numeric', 'gt:0', 'max:'.self::MAX_THRESHOLD, 'decimal:0,2'];
            $rules['guarantee_months'] = ['required', 'integer', 'min:0', 'max:'.config('loyalty.max_guarantee_months')];
        }

        return $rules;
    }

    /**
     * The threshold must stay between the tiers below and above it, or a tier could never
     * be reached.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $level = $this->tier()->tier_level;
            if ($level->isBase() || $validator->errors()->isNotEmpty()) {
                return;
            }

            $threshold = Amount::of($this->input('spending_threshold'));
            [$lower, $higher] = $this->neighbours($level);

            if ($lower && ! $lower->tier_level->isBase() && Amount::compare($threshold, Amount::of($lower->spending_threshold)) <= 0) {
                $validator->errors()->add('spending_threshold', __('Must be more than :tier (:amount).', ['tier' => $lower->tier_level->label(), 'amount' => money($lower->spending_threshold)]));
            }
            if ($higher && Amount::compare($threshold, Amount::of($higher->spending_threshold)) >= 0) {
                $validator->errors()->add('spending_threshold', __('Must be less than :tier (:amount).', ['tier' => $higher->tier_level->label(), 'amount' => money($higher->spending_threshold)]));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'spending_threshold' => __('spending per cycle'),
            'guarantee_months' => __('guarantee'),
        ];
    }

    public function tier(): LoyaltyTier
    {
        return $this->route('tier');
    }

    /**
     * The configured tiers directly below and above $level.
     *
     * @return array{0: LoyaltyTier|null, 1: LoyaltyTier|null}
     */
    private function neighbours(TierLevel $level): array
    {
        $tiers = app(TierConfigService::class)->tiers();

        return [
            $tiers->filter(fn (LoyaltyTier $tier) => $level->isAbove($tier->tier_level))->last(),
            $tiers->first(fn (LoyaltyTier $tier) => $tier->tier_level->isAbove($level)),
        ];
    }
}
