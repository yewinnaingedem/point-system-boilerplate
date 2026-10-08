<span class="badge badge-{{ $exchange->status->badge() }}">{{ __($exchange->status->label()) }}</span>
@if ($exchange->cancel_reason)
    <div class="small text-muted">{{ $exchange->cancel_reason }}</div>
@endif
