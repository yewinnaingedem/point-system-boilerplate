@if ($active)
    <span class="badge badge-success">{{ __('Active') }}</span>
@else
    <span class="badge badge-secondary">{{ __('Deactivated') }}</span>
@endif
