<div class="btn-group">
    @can('edit-merchant')
        <a href="{{ route('admin.merchants.rewards.edit', $reward) }}" class="btn btn-info btn-sm" title="{{ __('Edit') }}"><i class="fas fa-pencil-alt"></i></a>
    @endcan
    @can('delete-merchant')
        <x-confirm-delete :action="route('admin.merchants.rewards.destroy', $reward)" :title="__('Delete reward :name?', ['name' => $reward->name])" />
    @endcan
</div>
