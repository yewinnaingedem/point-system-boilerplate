@foreach ($user->roles as $role)
    <span class="badge badge-primary">{{ $role->name }}</span>
@endforeach
