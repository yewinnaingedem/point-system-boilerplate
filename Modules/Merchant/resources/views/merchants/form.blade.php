@php($editing = $merchant->exists)
<x-layouts.admin :title="$editing ? __('Edit :name', ['name' => $merchant->name]) : __('New merchant')"
                 :breadcrumbs="[__('Merchants') => route('admin.merchants.index')] + ($editing ? [$merchant->name => route('admin.merchants.show', $merchant)] : [])">
    <form method="POST" action="{{ $editing ? route('admin.merchants.update', $merchant) : route('admin.merchants.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="row">
            <div class="col-lg-8">
                <div class="card card-primary card-outline">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-store mr-1"></i> {{ __('Merchant') }}</h3></div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="name">{{ __('Name') }}</label>
                                <input id="name" name="name" value="{{ old('name', $merchant->name) }}" required maxlength="150" placeholder="{{ __('e.g. KFC') }}" @class(['form-control', 'is-invalid' => $errors->has('name')])>
                                @error('name') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label for="contact_person">{{ __('Contact person') }}</label>
                                <input id="contact_person" name="contact_person" value="{{ old('contact_person', $merchant->contact_person) }}" maxlength="150" @class(['form-control', 'is-invalid' => $errors->has('contact_person')])>
                                @error('contact_person') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label for="phone">{{ __('Phone') }}</label>
                                <input id="phone" name="phone" value="{{ old('phone', $merchant->phone) }}" maxlength="50" @class(['form-control', 'is-invalid' => $errors->has('phone')])>
                                @error('phone') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label for="email">{{ __('Email') }}</label>
                                <input type="email" id="email" name="email" value="{{ old('email', $merchant->email) }}" maxlength="150" @class(['form-control', 'is-invalid' => $errors->has('email')])>
                                @error('email') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-group col-12">
                                <label for="address">{{ __('Head office address') }}</label>
                                <textarea id="address" name="address" rows="2" maxlength="500" @class(['form-control', 'is-invalid' => $errors->has('address')])>{{ old('address', $merchant->address) }}</textarea>
                                @error('address') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            </div>
                            @unless (auth()->user()->isMerchantUser())
                                <div class="form-group col-12 mb-0">
                                    <label for="notes">{{ __('Notes') }}</label>
                                    <textarea id="notes" name="notes" rows="2" maxlength="2000" @class(['form-control', 'is-invalid' => $errors->has('notes')])>{{ old('notes', $merchant->notes) }}</textarea>
                                    @error('notes') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                            @endunless
                        </div>
                    </div>
                </div>
            </div>
            @unless (auth()->user()->isMerchantUser())
            <div class="col-lg-4">
                <div class="card card-secondary card-outline">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-hand-holding-usd mr-1"></i> {{ __('Payout') }}</h3></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="settlement_rate">{{ __('Payout per point (:currency)', ['currency' => setting('currency_code')]) }}</label>
                            <input type="number" min="0" step="0.0001" id="settlement_rate" name="settlement_rate" value="{{ old('settlement_rate', (float) $merchant->settlement_rate) }}" required
                                   @class(['form-control', 'is-invalid' => $errors->has('settlement_rate')])>
                            @error('settlement_rate') <span class="invalid-feedback">{{ $message }}</span> @enderror
                            <small class="form-text text-muted">{{ __('What we pay this merchant for each point a member spends there. A reward can set its own payout instead.') }}</small>
                        </div>
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $merchant->is_active))>
                            <label class="custom-control-label" for="is_active">{{ __('Active (members can redeem here)') }}</label>
                        </div>
                    </div>
                </div>
            </div>
            @endunless
        </div>
        <div class="mb-4">
            <a href="{{ $editing ? route('admin.merchants.show', $merchant) : route('admin.merchants.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary float-right"><i class="fas fa-save mr-1"></i>{{ $editing ? __('Save changes') : __('Create merchant') }}</button>
        </div>
    </form>
</x-layouts.admin>
