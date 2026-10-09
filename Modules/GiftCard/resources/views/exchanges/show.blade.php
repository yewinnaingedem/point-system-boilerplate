@php($status = $exchange->status)
@php($S = \Modules\GiftCard\Enums\ExchangeStatus::class)
<x-layouts.admin :title="$exchange->code ?? __('Exchange #:id', ['id' => $exchange->id])"
                 :breadcrumbs="[__('Gift Cards') => route('admin.gift-cards.index'), __('Exchanges') => route('admin.gift-card-exchanges.index')]">
    <div class="row">
        <div class="col-lg-8">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title mt-1">
                        <i class="fas fa-gift mr-1"></i> {{ $exchange->giftCard->name }}
                        <span class="badge badge-{{ $status->badge() }} ml-1">{{ __($status->label()) }}</span>
                    </h3>
                    <div class="card-tools no-print">
                        <button type="button" class="btn btn-default btn-sm" data-print><i class="fas fa-print mr-1"></i>{{ __('Print') }}</button>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('Gift card code') }}</dt>
                        <dd class="col-sm-8">@if ($exchange->code) <code class="h6">{{ $exchange->code }}</code> @else <span class="text-muted">{{ __('Not issued yet') }}</span> @endif</dd>
                        <dt class="col-sm-4">{{ __('Can be used at') }}</dt>
                        <dd class="col-sm-8">{{ $exchange->giftCard->merchant?->name ?? __('Any partner shop') }}</dd>
                        <dt class="col-sm-4">{{ __('Customer') }}</dt>
                        <dd class="col-sm-8">
                            {{ $exchange->customer?->name }}
                            <span class="text-muted small">{{ collect([$exchange->customer?->external_id, $exchange->customer?->email, $exchange->customer?->phone])->filter()->implode(' · ') }}</span>
                        </dd>
                        <dt class="col-sm-4">{{ __('Points') }}</dt>
                        <dd class="col-sm-8">{{ number_format($exchange->points) }}</dd>
                        <dt class="col-sm-4">{{ __('Value') }}</dt>
                        <dd class="col-sm-8 font-weight-bold">{{ money($exchange->face_value, 2) }}</dd>
                        <dt class="col-sm-4">{{ __('Requested') }}</dt>
                        <dd class="col-sm-8">{{ $exchange->created_at->format(setting('date_format').' H:i') }}</dd>
                        @if ($exchange->issued_at)
                            <dt class="col-sm-4">{{ __('Issued') }}</dt>
                            <dd class="col-sm-8">{{ $exchange->issued_at->format(setting('date_format').' H:i') }}</dd>
                        @endif
                        @if ($exchange->code)
                            <dt class="col-sm-4">{{ __('Valid until') }}</dt>
                            <dd class="col-sm-8">
                                {{ $exchange->expires_at?->format(setting('date_format')) ?? __('No expiry') }}
                                @if ($status === $S::Issued && $exchange->expires_at?->isPast()) <span class="badge badge-secondary ml-1">{{ __('Expired') }}</span> @endif
                            </dd>
                        @endif
                        @if ($status === $S::Pending)
                            <dt class="col-sm-4">{{ __('Code must be entered by') }}</dt>
                            <dd class="col-sm-8">{{ $exchange->verification_expires_at?->format(setting('date_format').' H:i') }}</dd>
                        @endif
                        @if ($status === $S::Used)
                            <dt class="col-sm-4">{{ __('Used at') }}</dt>
                            <dd class="col-sm-8">{{ $exchange->merchant?->name }} {{ $exchange->branch?->name }} · {{ $exchange->used_at?->format(setting('date_format').' H:i') }}</dd>
                            <dt class="col-sm-4">{{ __('Owed to the merchant') }}</dt>
                            <dd class="col-sm-8">
                                {{ money($exchange->payout_amount, 2) }}
                                @if ($exchange->claim)
                                    ·
                                    @can('view-merchantclaim')
                                        <a href="{{ route('admin.claims.show', $exchange->claim) }}">{{ $exchange->claim->reference }}</a>
                                    @else
                                        {{ $exchange->claim->reference }}
                                    @endcan
                                    <span class="badge badge-{{ $exchange->claim->status->badge() }}">{{ __($exchange->claim->status->label()) }}</span>
                                @else
                                    · <span class="text-muted">{{ __('not claimed yet') }}</span>
                                @endif
                            </dd>
                        @endif
                        @if ($status === $S::Cancelled)
                            <dt class="col-sm-4">{{ __('Cancelled') }}</dt>
                            <dd class="col-sm-8 mb-0">
                                {{ $exchange->cancelled_at?->format(setting('date_format').' H:i') }} · {{ $exchange->canceller?->name ?? '—' }}
                                <div class="text-muted">{{ $exchange->cancel_reason }}</div>
                            </dd>
                        @endif
                    </dl>
                </div>
                <div class="card-footer no-print">
                    <a href="{{ route('admin.gift-card-exchanges.index') }}" class="btn btn-secondary">{{ __('Back') }}</a>
                </div>
            </div>
        </div>

        <div class="col-lg-4 no-print">
            @if ($status === $S::Pending)
                @can('create-giftcardexchange')
                    <form method="POST" action="{{ route('admin.gift-card-exchanges.verify', $exchange) }}" class="card card-warning card-outline">
                        @csrf
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-shield-alt mr-1"></i> {{ __('Emailed code') }}</h3></div>
                        <div class="card-body">
                            <p class="text-muted">{{ __('This card needs the 6-digit code emailed to the customer. Ask them for it.') }}</p>
                            <input name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" required placeholder="000000" class="form-control text-center h5" autocomplete="one-time-code">
                        </div>
                        <div class="card-footer text-right">
                            <button type="submit" class="btn btn-warning"><i class="fas fa-check mr-1"></i>{{ __('Issue gift card') }}</button>
                        </div>
                    </form>
                @endcan
            @elseif ($status === $S::Issued)
                @can('cancel-giftcardexchange')
                    <form method="POST" action="{{ route('admin.gift-card-exchanges.cancel', $exchange) }}" class="card card-danger card-outline"
                          data-confirm="{{ __('Cancel :code? Its :points points go back to the customer and the stock is returned.', ['code' => $exchange->code, 'points' => number_format($exchange->points)]) }}">
                        @csrf
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-undo mr-1"></i> {{ __('Cancel and refund') }}</h3></div>
                        <div class="card-body">
                            <p class="text-muted">{{ __(':points points go back to the customer (into the same lots) and the stock is returned.', ['points' => number_format($exchange->points)]) }}</p>
                            <label for="reason">{{ __('Reason') }}</label>
                            <input id="reason" name="reason" required maxlength="255" @class(['form-control', 'is-invalid' => $errors->has('reason')])>
                            @error('reason') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        </div>
                        <div class="card-footer text-right">
                            <button type="submit" class="btn btn-outline-danger">{{ __('Cancel gift card') }}</button>
                        </div>
                    </form>
                @endcan
            @elseif ($status === $S::Used)
                <div class="callout callout-info">
                    <h5><i class="fas fa-store mr-1"></i> {{ __('Used') }}</h5>
                    <p class="mb-0">{{ __('The shop already handed over the goods, so a used gift card can\'t be cancelled.') }}</p>
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
