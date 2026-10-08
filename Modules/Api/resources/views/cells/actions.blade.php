@can('delete-apitoken')
    <div class="btn-group">
        @if ($owner)
            <form method="POST" action="{{ route('admin.api-tokens.destroy-user', $owner) }}" class="d-inline">
                @csrf
                @method('DELETE')
                <button class="btn btn-warning btn-sm" title="{{ __('Sign out of all devices') }}"><i class="fas fa-user-slash"></i></button>
            </form>
        @endif
        <x-confirm-delete :action="route('admin.api-tokens.destroy', $token)" :title="__('Sign out device :name?', ['name' => $token->name])" />
    </div>
@endcan
