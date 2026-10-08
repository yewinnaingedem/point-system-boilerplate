@if ($token->expires_at?->isPast())
    <span class="badge badge-secondary">{{ __('Expired') }}</span>
@elseif ($token->expires_at)
    <span class="badge badge-success">{{ $token->expires_at->diffForHumans() }}</span>
@else
    <span class="badge badge-warning">{{ __('Never') }}</span>
@endif
