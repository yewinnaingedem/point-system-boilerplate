<div class="btn-group">
    @can('view-giftcardexchange')
        <a href="{{ route('admin.gift-card-exchanges.index', ['gift_card_id' => $card->id]) }}" class="btn btn-default btn-sm" title="{{ __('Exchanges') }}"><i class="fas fa-list"></i></a>
    @endcan
    @can('edit-giftcard')
        <a href="{{ route('admin.gift-cards.edit', $card) }}" class="btn btn-info btn-sm" title="{{ __('Edit') }}"><i class="fas fa-pencil-alt"></i></a>
    @endcan
    @can('delete-giftcard')
        <x-confirm-delete :action="route('admin.gift-cards.destroy', $card)" :title="__('Delete gift card :name?', ['name' => $card->name])" />
    @endcan
</div>
