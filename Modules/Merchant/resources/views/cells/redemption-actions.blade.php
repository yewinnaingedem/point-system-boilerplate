<div class="text-muted small">{{ $redemption->reference }}</div>
@can('reverse-redemption')
    @if ($redemption->isReversible())
        <a href="{{ route('admin.redemptions.reverse.create', $redemption) }}" class="btn btn-outline-danger btn-sm" title="{{ __('Reverse') }}"><i class="fas fa-undo"></i></a>
    @endif
@endcan
