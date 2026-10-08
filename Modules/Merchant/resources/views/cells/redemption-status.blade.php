<span class="badge badge-{{ $redemption->status->badge() }}">{{ __($redemption->status->label()) }}</span>
@if ($redemption->settlement_id)
    <span class="badge badge-info">{{ __('Settled') }}</span>
@elseif ($redemption->status === \Modules\Merchant\Enums\RedemptionStatus::Completed)
    <span class="badge badge-warning">{{ __('Unsettled') }}</span>
@endif
