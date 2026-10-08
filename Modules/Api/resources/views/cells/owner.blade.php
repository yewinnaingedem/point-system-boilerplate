@if ($owner)
    <div class="d-flex align-items-center">
        <x-avatar :user="$owner" size="32" class="mr-2" />
        <div>
            <div class="font-weight-bold">{{ $owner->name }}</div>
            <div class="small text-muted">{{ $owner->email }}</div>
        </div>
    </div>
@else
    <span class="text-muted">{{ __('Deleted user') }}</span>
@endif
