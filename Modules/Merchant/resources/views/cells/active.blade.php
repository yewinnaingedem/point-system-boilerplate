@if ($active)
    <span class="badge badge-success">{{ __('Active') }}</span>
@else
    <span class="badge badge-secondary">{{ __('Inactive') }}</span>
@endif
