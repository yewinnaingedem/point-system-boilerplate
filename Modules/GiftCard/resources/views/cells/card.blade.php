<div class="font-weight-bold">{{ $card->name }}</div>
<div class="small"><i class="fas fa-store mr-1 text-muted"></i>{{ $card->merchant?->name ?? __('Any partner shop') }}</div>
@if ($card->description)
    <div class="small text-muted text-wrap" style="max-width: 22rem">{{ $card->description }}</div>
@endif
