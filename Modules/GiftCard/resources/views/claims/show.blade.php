<x-layouts.admin :title="$claim->reference"
                 :breadcrumbs="[__('Merchants') => route('admin.merchants.index'), __('Claims') => route('admin.claims.index')]">
    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title mt-1">
                <i class="fas fa-file-invoice-dollar mr-1"></i> {{ __('Claim from :name', ['name' => $claim->merchant->name]) }}
                <span class="badge badge-{{ $claim->status->badge() }} ml-1">{{ __($claim->status->label()) }}</span>
            </h3>
            <div class="card-tools no-print">
                @if ($claim->isOpen())
                    @can('edit-merchantclaim')
                        <a href="{{ route('admin.claims.edit', $claim) }}" class="btn btn-info btn-sm"><i class="fas fa-pencil-alt mr-1"></i>{{ __('Edit') }}</a>
                    @endcan
                    @can('delete-merchantclaim')
                        <x-confirm-delete :action="route('admin.claims.destroy', $claim)" :title="__('Delete claim :reference? Its gift cards can be claimed again.', ['reference' => $claim->reference])" />
                    @endcan
                @endif
                <button type="button" class="btn btn-default btn-sm" data-print><i class="fas fa-print mr-1"></i>{{ __('Print') }}</button>
                <a href="{{ route('admin.claims.export', $claim) }}" class="btn btn-default btn-sm"><i class="fas fa-file-csv mr-1"></i>{{ __('CSV') }}</a>
            </div>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">{{ __('Amount') }}</dt>
                <dd class="col-sm-9 font-weight-bold">{{ money($claim->amount, 2) }} <span class="text-muted font-weight-normal">({{ trans_choice('1 gift card|:count gift cards', $claim->cards_count) }})</span></dd>
                <dt class="col-sm-3">{{ __('Gift cards used up to') }}</dt>
                <dd class="col-sm-9">{{ $claim->up_to?->format(setting('date_format')) }}</dd>
                @if ($claim->note)
                    <dt class="col-sm-3">{{ __('Note') }}</dt>
                    <dd class="col-sm-9">{{ $claim->note }}</dd>
                @endif
                <dt class="col-sm-3">{{ __('Created') }}</dt>
                <dd class="col-sm-9">{{ $claim->created_at->format(setting('date_format').' H:i') }} · {{ $claim->creator?->name ?? '—' }}</dd>
                @if ($claim->paid_on)
                    <dt class="col-sm-3">{{ __('Paid on') }}</dt>
                    <dd class="col-sm-9">{{ $claim->paid_on->format(setting('date_format')) }}{{ $claim->payment_reference ? " · {$claim->payment_reference}" : '' }}</dd>
                @endif
                @if ($claim->reject_reason)
                    <dt class="col-sm-3">{{ __('Rejected') }}</dt>
                    <dd class="col-sm-9 text-danger">{{ $claim->reject_reason }}</dd>
                @endif
                @if ($claim->decided_at)
                    <dt class="col-sm-3">{{ __('Decided by') }}</dt>
                    <dd class="col-sm-9 mb-0">{{ $claim->decider?->name ?? '—' }}, {{ $claim->decided_at->format(setting('date_format').' H:i') }}</dd>
                @endif
            </dl>
        </div>
    </div>

    @if ($claim->isOpen() && ! auth()->user()->isMerchantUser())
        @can('settle-merchantclaim')
            <div class="row no-print">
                <div class="col-lg-7">
                    <form method="POST" action="{{ route('admin.claims.pay', $claim) }}" class="card card-success card-outline"
                          data-confirm="{{ __('Mark :amount to :name as paid?', ['amount' => money($claim->amount, 2), 'name' => $claim->merchant->name]) }}">
                        @csrf
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-money-check-alt mr-1"></i> {{ __('Pay') }}</h3></div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group col-md-5 mb-0">
                                    <label for="paid_on">{{ __('Paid on') }}</label>
                                    <input type="date" id="paid_on" name="paid_on" value="{{ old('paid_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required @class(['form-control', 'is-invalid' => $errors->has('paid_on')])>
                                    @error('paid_on') <span class="invalid-feedback">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-group col-md-7 mb-0">
                                    <label for="payment_reference">{{ __('Payment reference') }}</label>
                                    <input id="payment_reference" name="payment_reference" value="{{ old('payment_reference') }}" maxlength="100" placeholder="{{ __('e.g. bank transfer number') }}" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i>{{ __('Mark :amount as paid', ['amount' => money($claim->amount, 2)]) }}</button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-5">
                    <form method="POST" action="{{ route('admin.claims.reject', $claim) }}" class="card card-danger card-outline">
                        @csrf
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-times mr-1"></i> {{ __('Reject') }}</h3></div>
                        <div class="card-body">
                            <label for="reject_reason">{{ __('Reason') }}</label>
                            <input id="reject_reason" name="reject_reason" value="{{ old('reject_reason') }}" maxlength="255" required @class(['form-control', 'is-invalid' => $errors->has('reject_reason')])>
                            @error('reject_reason') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                        <div class="card-footer text-right">
                            <button type="submit" class="btn btn-outline-danger">{{ __('Reject claim') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    @endif

    <div class="card card-secondary card-outline">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-list mr-1"></i> {{ __('Gift cards') }}</h3></div>
        <div class="card-body p-0 table-responsive">
            @if ($claim->status === \Modules\GiftCard\Enums\ClaimStatus::Rejected)
                <p class="text-center text-muted py-4 mb-0">{{ __('This claim was rejected; its :count gift cards were released and can be claimed again.', ['count' => $claim->cards_count]) }}</p>
            @else
                @include('giftcard::claims._cards', ['count' => $claim->cards_count, 'total' => $claim->amount, 'empty' => '—'])
            @endif
        </div>
        @if ($cards->hasPages())
            <div class="card-footer clearfix no-print">{{ $cards->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
