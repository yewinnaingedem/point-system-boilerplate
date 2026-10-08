<div class="small">{{ $card->per_customer_limit ? trans_choice('{1} Once per customer|[2,*] :count per customer', $card->per_customer_limit) : __('No limit per customer') }}</div>
@if ($card->requires_verification)
    <span class="badge badge-info"><i class="fas fa-shield-alt mr-1"></i>{{ __('2-step') }}</span>
@endif
@if ($card->valid_days)
    <span class="badge badge-light">{{ trans_choice('{1} valid 1 day|[2,*] valid :count days', $card->valid_days) }}</span>
@endif
