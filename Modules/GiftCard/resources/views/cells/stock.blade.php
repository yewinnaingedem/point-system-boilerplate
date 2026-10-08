@if ($card->stock === null)
    <span class="text-muted">{{ __('Unlimited') }}</span>
@elseif ($card->isOutOfStock())
    <span class="badge badge-danger">{{ __('Out of stock') }}</span>
@else
    {{ number_format($card->stock) }}
@endif
<div class="small text-muted">{{ trans_choice('{0} none issued|{1} 1 issued|[2,*] :count issued', $card->issued_count ?? 0) }}</div>
