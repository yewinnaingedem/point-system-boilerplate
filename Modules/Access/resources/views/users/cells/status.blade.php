@if ($deleted)
    <span class="badge badge-danger">{{ __('Deleted') }}</span>
@elseif ($user->is_active)
    <span class="badge badge-success">{{ __('Active') }}</span>
@else
    <span class="badge badge-secondary">{{ __('Deactivated') }}</span>
@endif
