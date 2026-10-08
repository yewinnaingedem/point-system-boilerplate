<div class="btn-group">
    @if ($deleted)
        @can('restore', $user)
            <form method="POST" action="{{ route('admin.access.users.restore', $user) }}" class="d-inline">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-success btn-sm" title="{{ __('Restore') }}"><i class="fas fa-trash-restore"></i></button>
            </form>
            <x-confirm-delete :action="route('admin.access.users.force-delete', $user)" :title="__('Permanently delete :name? This cannot be undone.', ['name' => $user->name])" />
        @endcan
    @else
        <a href="{{ route('admin.access.users.show', $user) }}" class="btn btn-default btn-sm" title="{{ __('View') }}"><i class="fas fa-eye"></i></a>
        @can('update', $user)
            <a href="{{ route('admin.access.users.edit', $user) }}" class="btn btn-info btn-sm" title="{{ __('Edit') }}"><i class="fas fa-pencil-alt"></i></a>
        @endcan
        @can('impersonate', $user)
            <form method="POST" action="{{ route('admin.access.users.impersonate', $user) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-warning btn-sm" title="{{ __('Login as :name', ['name' => $user->name]) }}"><i class="fas fa-user-secret"></i></button>
            </form>
        @endcan
        @can('delete', $user)
            @unless ($user->is(auth()->user()))
                <x-confirm-delete :action="route('admin.access.users.destroy', $user)" :title="__('Delete :name?', ['name' => $user->name])" />
            @endunless
        @endcan
    @endif
</div>
