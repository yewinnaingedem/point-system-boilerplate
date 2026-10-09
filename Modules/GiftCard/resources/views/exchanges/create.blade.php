<x-layouts.admin :title="__('Exchange for a customer')"
                 :breadcrumbs="[__('Gift Cards') => route('admin.gift-cards.index'), __('Exchanges') => route('admin.gift-card-exchanges.index')]">
    <form method="POST" action="{{ route('admin.gift-card-exchanges.store') }}">
        @csrf
        <div class="row">
            <div class="col-lg-8">
                <div class="card card-primary card-outline">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-exchange-alt mr-1"></i> {{ __('Exchange points for a gift card') }}</h3></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="customer_id">{{ __('Customer') }}</label>
                            @include('customer::partials.select', ['name' => 'customer_id', 'selected' => $customer])
                        </div>
                        <div class="form-group mb-0">
                            <label for="gift_card_id">{{ __('Gift card') }}</label>
                            <select id="gift_card_id" name="gift_card_id" required @class(['custom-select', 'is-invalid' => $errors->has('gift_card_id')])>
                                <option value="">{{ __('Choose…') }}</option>
                                @foreach ($cards as $card)
                                    <option value="{{ $card->id }}" @selected((string) old('gift_card_id', $selected) === (string) $card->id) @disabled($card->isOutOfStock())>
                                        {{ $card->name }}{{ $card->merchant ? " ({$card->merchant->name})" : '' }} — {{ number_format($card->points_cost) }} {{ __('points') }} · {{ money($card->face_value, 2) }}
                                        {{ $card->min_tier ? '· '.__(':tier and up', ['tier' => ucfirst($card->min_tier->value)]) : '' }}
                                        {{ $card->requires_verification ? '· '.__('emailed code') : '' }}
                                        {{ $card->isOutOfStock() ? '· '.__('out of stock') : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('gift_card_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('admin.gift-card-exchanges.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                        <button type="submit" class="btn btn-primary float-right"><i class="fas fa-check mr-1"></i>{{ __('Exchange') }}</button>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="callout callout-info">
                    <h5><i class="fas fa-info-circle mr-1"></i> {{ __('Same rules as the app') }}</h5>
                    <p class="mb-0">{{ __('The customer\'s tier, the stock, the per-customer limit and their points are checked, and the points are taken from the soonest-expiring first. A card with two-step verification emails the customer a code; you enter it on the next page.') }}</p>
                </div>
            </div>
        </div>
    </form>
</x-layouts.admin>
