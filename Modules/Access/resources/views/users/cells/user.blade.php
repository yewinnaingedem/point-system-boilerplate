@if ($deleted)
    <div class="font-weight-bold">{{ $user->name }}</div>
@else
    <a href="{{ route('admin.access.users.show', $user) }}" class="font-weight-bold">{{ $user->name }}</a>
@endif
<div class="small text-muted">{{ $user->email }}</div>
