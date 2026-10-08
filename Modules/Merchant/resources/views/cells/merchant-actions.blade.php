<div class="btn-group">
    <a href="{{ route('admin.merchants.show', $merchant) }}" class="btn btn-default btn-sm" title="{{ __('View') }}"><i class="fas fa-eye"></i></a>
    @can('edit-merchant')
        <a href="{{ route('admin.merchants.edit', $merchant) }}" class="btn btn-info btn-sm" title="{{ __('Edit') }}"><i class="fas fa-pencil-alt"></i></a>
    @endcan
    @can('delete-merchant')
        <x-confirm-delete :action="route('admin.merchants.destroy', $merchant)" :title="__('Delete :name with all its branches and rewards?', ['name' => $merchant->name])" />
    @endcan
</div>
