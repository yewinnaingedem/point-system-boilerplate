{{ money($reward->payoutFor($merchant), 2) }}
@if ($reward->payout_amount === null)
    <div class="small text-muted">{{ __('by rate') }}</div>
@endif
