<x-layouts.admin :title="__('Reverse :reference', ['reference' => $redemption->reference])" :breadcrumbs="[__('Redemptions') => route('admin.redemptions.index')]">
    <div class="">
        <div class="">
            <form method="POST" action="{{ route('admin.redemptions.reverse.store', $redemption) }}" class="card card-danger card-outline">
                @csrf
                <div class="card-body">
                    <dl class="row mb-3">
                        <dt class="col-sm-4">{{ __('Customer') }}</dt><dd class="col-sm-8">{{ $redemption->customer->name }}</dd>
                        <dt class="col-sm-4">{{ __('Shop') }}</dt><dd class="col-sm-8">{{ $redemption->merchant->name }} · {{ $redemption->branch->name }}</dd>
                        <dt class="col-sm-4">{{ __('Reward') }}</dt><dd class="col-sm-8">{{ $redemption->reward_name }}</dd>
                        <dt class="col-sm-4">{{ __('Points') }}</dt><dd class="col-sm-8">{{ number_format($redemption->points) }}</dd>
                        <dt class="col-sm-4">{{ __('Redeemed') }}</dt><dd class="col-sm-8">{{ $redemption->redeemed_at->format(setting('date_format').' H:i') }}</dd>
                    </dl>
                    <p class="text-muted">{{ __('The points go back to the customer and the merchant is no longer owed this redemption. Use this when the reward was not handed over.') }}</p>
                    <div class="form-group mb-0">
                        <label for="reason">{{ __('Reason') }}</label>
                        <input id="reason" name="reason" value="{{ old('reason') }}" required maxlength="255" @class(['form-control', 'is-invalid' => $errors->has('reason')])>
                        @error('reason') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="card-footer clearfix">
                    <a href="{{ route('admin.redemptions.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-danger float-right"><i class="fas fa-undo mr-1"></i>{{ __('Reverse redemption') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
