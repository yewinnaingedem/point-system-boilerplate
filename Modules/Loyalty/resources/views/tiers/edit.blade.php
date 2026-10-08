@php
    $level = $tier->tier_level;
    $color = old('color', $tier->color);
@endphp
<x-layouts.admin :title="__('Edit :tier tier', ['tier' => $level->label()])"
                 :breadcrumbs="[__('Loyalty Tiers') => route('admin.loyalty.tiers.index')]">
    <div class="">
        <div class="">
            <form method="POST" action="{{ route('admin.loyalty.tiers.update', $tier) }}" class="card card-primary card-outline">
                @csrf
                @method('PUT')
                <div class="card-header">
                    <h3 class="card-title mt-1">@include('loyalty::partials.tier-badge', ['tier' => $tier])</h3>
                </div>
                <div class="card-body">
                    @if ($level->isBase())
                        <p class="text-muted">{{ __('Every member starts in this tier, so it has no spending threshold and no guarantee.') }}</p>
                    @else
                        <div class="form-group">
                            <label for="spending_threshold">{{ __('Spending per cycle (:currency)', ['currency' => setting('currency_code')]) }}</label>
                            <input type="number" min="0" step="0.01" id="spending_threshold" name="spending_threshold"
                                   value="{{ old('spending_threshold', (float) $tier->spending_threshold) }}"
                                   @class(['form-control', 'is-invalid' => $errors->has('spending_threshold')])>
                            @error('spending_threshold') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            <small class="form-text text-muted">{{ __('Reaching this within one cycle moves a member up immediately.') }}</small>
                        </div>
                        <div class="form-group">
                            <label for="guarantee_months">{{ __('Guarantee (months)') }}</label>
                            <input type="number" min="0" max="{{ config('loyalty.max_guarantee_months') }}" step="1" id="guarantee_months" name="guarantee_months"
                                   value="{{ old('guarantee_months', $tier->guarantee_months) }}"
                                   @class(['form-control', 'is-invalid' => $errors->has('guarantee_months')])>
                            @error('guarantee_months') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            <small class="form-text text-muted">{{ __('How long the tier is kept after reaching it, even if spending drops. 0 = no guarantee.') }}</small>
                        </div>
                    @endif
                    <div class="form-group mb-0">
                        <label for="color">{{ __('Color') }}</label>
                        <div class="input-group" style="max-width: 16rem">
                            <div class="input-group-prepend">
                                <input type="color" value="{{ $color }}" class="form-control settings-color" data-sync="#color" id="color_picker">
                            </div>
                            <input id="color" name="color" value="{{ $color }}" maxlength="7" data-synced-by="#color_picker"
                                   @class(['form-control text-monospace', 'is-invalid' => $errors->has('color')])>
                            @error('color') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                        <small class="form-text text-muted">{{ __('Used for this tier\'s badge everywhere in the app.') }}</small>
                    </div>
                </div>
                <div class="card-footer clearfix">
                    <a href="{{ route('admin.loyalty.tiers.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary float-right"><i class="fas fa-save mr-1"></i>{{ __('Save changes') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
