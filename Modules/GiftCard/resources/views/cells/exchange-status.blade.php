<span class="badge badge-{{ $exchange->status->badge() }}">{{ __($exchange->status->label()) }}</span>
@if ($exchange->status === \Modules\GiftCard\Enums\ExchangeStatus::Used)
    <div class="small text-muted">{{ __('at :branch', ['branch' => "{$exchange->merchant?->name} {$exchange->branch?->name}"]) }} · {{ $exchange->used_at?->format(setting('date_format').' H:i') }}</div>
@endif
@if ($exchange->cancel_reason)
    <div class="small text-muted">{{ $exchange->cancel_reason }}</div>
@endif
