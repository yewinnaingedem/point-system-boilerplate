@can('view-merchantcode')
    <div class="input-group input-group-sm" style="width: 9.5rem">
        <input type="password" readonly value="{{ $branch->code }}" id="branch-code-{{ $branch->id }}" class="form-control text-monospace" aria-label="{{ __('Branch code') }}">
        <div class="input-group-append">
            <button type="button" class="btn btn-default" data-toggle-password="#branch-code-{{ $branch->id }}" title="{{ __('Show / hide') }}"><i class="fas fa-eye"></i></button>
        </div>
    </div>
    <div class="small text-muted">{{ __('Changed :when', ['when' => $branch->code_changed_at->diffForHumans()]) }}</div>
@else
    <span class="text-muted text-monospace">••••••</span>
@endcan
