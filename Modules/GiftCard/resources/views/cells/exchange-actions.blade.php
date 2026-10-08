@can('cancel-giftcardexchange')
    @if ($exchange->status === \Modules\GiftCard\Enums\ExchangeStatus::Issued)
        <form method="POST" action="{{ route('admin.gift-card-exchanges.cancel', $exchange) }}" class="form-inline justify-content-end"
              data-confirm="{{ __('Cancel :code? Its :points points go back to the customer and the stock is returned.', ['code' => $exchange->code, 'points' => number_format($exchange->points)]) }}">
            @csrf
            <input name="reason" required maxlength="255" class="form-control form-control-sm mr-1" style="width: 9rem" placeholder="{{ __('Reason') }}">
            <button type="submit" class="btn btn-outline-danger btn-sm" title="{{ __('Cancel and refund') }}"><i class="fas fa-undo"></i></button>
        </form>
    @endif
@endcan
