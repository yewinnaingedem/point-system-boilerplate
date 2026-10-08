<div class="btn-group">
    @can('edit-merchantcode')
        <form method="POST" action="{{ route('admin.merchants.branches.code', $branch) }}" class="d-inline"
              data-confirm="{{ __('Give :name a new code? The current code stops working immediately.', ['name' => $branch->name]) }}">
            @csrf
            <button type="submit" class="btn btn-warning btn-sm" title="{{ __('New code') }}"><i class="fas fa-key"></i></button>
        </form>
    @endcan
    @can('edit-merchant')
        <a href="{{ route('admin.merchants.branches.edit', $branch) }}" class="btn btn-info btn-sm" title="{{ __('Edit') }}"><i class="fas fa-pencil-alt"></i></a>
    @endcan
    @can('delete-merchant')
        @if ($branch->redemptions_count === 0)
            <x-confirm-delete :action="route('admin.merchants.branches.destroy', $branch)" :title="__('Delete branch :name?', ['name' => $branch->name])" />
        @endif
    @endcan
</div>
