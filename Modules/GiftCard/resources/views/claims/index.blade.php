@php($merchantUser = auth()->user()->isMerchantUser())
<x-layouts.admin :title="__('Claims')" :breadcrumbs="[__('Merchants') => route('admin.merchants.index')]">
    <div class="row">
        <div class="col-md-4 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-inbox"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Ready to claim') }}</span>
                    <span class="info-box-number">{{ money($readyAmount, 2) }}</span>
                    <span class="small text-muted">{{ trans_choice('{0} no used gift cards|{1} 1 used gift card|[2,*] :count used gift cards', $readyCards) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-hourglass-half"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Submitted, not paid yet') }}</span>
                    <span class="info-box-number">{{ money($submittedAmount, 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="info-box">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-check"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Paid this month') }}</span>
                    <span class="info-box-number">{{ money($paidThisMonth, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title mt-1"><i class="fas fa-file-invoice-dollar mr-1"></i> {{ __('Claims') }}</h3>
            <div class="card-tools d-flex flex-wrap">
                <form method="GET" class="form-inline mr-2">
                    @unless ($merchantUser)
                        <select name="merchant_id" class="custom-select custom-select-sm mr-1" data-autosubmit>
                            <option value="">{{ __('All merchants') }}</option>
                            @foreach ($merchants as $id => $name)
                                <option value="{{ $id }}" @selected((string) ($filters['merchant_id'] ?? '') === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    @endunless
                    <select name="status" class="custom-select custom-select-sm" data-autosubmit>
                        <option value="">{{ __('Any status') }}</option>
                        @foreach (\Modules\GiftCard\Enums\ClaimStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ __($status->label()) }}</option>
                        @endforeach
                    </select>
                </form>
                @can('create-merchantclaim')
                    <a href="{{ route('admin.claims.create', array_filter(['merchant_id' => $filters['merchant_id'] ?? null])) }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>{{ __('New claim') }}</a>
                @endcan
            </div>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-striped mb-0 text-nowrap">
                <thead>
                    <tr>
                        <th>{{ __('Reference') }}</th>
                        @unless ($merchantUser) <th>{{ __('Merchant') }}</th> @endunless
                        <th>{{ __('Cards used up to') }}</th>
                        <th class="text-right">{{ __('Gift cards') }}</th>
                        <th class="text-right">{{ __('Amount') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Created') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($claims as $claim)
                        <tr>
                            <td><a href="{{ route('admin.claims.show', $claim) }}" class="font-weight-bold">{{ $claim->reference }}</a></td>
                            @unless ($merchantUser) <td>{{ $claim->merchant->name }}</td> @endunless
                            <td>{{ $claim->up_to?->format(setting('date_format')) }}</td>
                            <td class="text-right">{{ number_format($claim->cards_count) }}</td>
                            <td class="text-right font-weight-bold">{{ money($claim->amount, 2) }}</td>
                            <td>
                                <span class="badge badge-{{ $claim->status->badge() }}">{{ __($claim->status->label()) }}</span>
                                @if ($claim->paid_on) <div class="small text-muted">{{ $claim->paid_on->format(setting('date_format')) }}</div> @endif
                            </td>
                            <td class="text-muted">{{ $claim->created_at->format(setting('date_format')) }} · {{ $claim->creator?->name ?? '—' }}</td>
                            <td class="text-right">
                                <a href="{{ route('admin.claims.show', $claim) }}" class="btn btn-default btn-sm" title="{{ __('View') }}"><i class="fas fa-eye"></i></a>
                                @if ($claim->isOpen())
                                    @can('edit-merchantclaim')
                                        <a href="{{ route('admin.claims.edit', $claim) }}" class="btn btn-info btn-sm" title="{{ __('Edit') }}"><i class="fas fa-pencil-alt"></i></a>
                                    @endcan
                                    @can('delete-merchantclaim')
                                        <x-confirm-delete :action="route('admin.claims.destroy', $claim)" :title="__('Delete claim :reference? Its gift cards can be claimed again.', ['reference' => $claim->reference])" />
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No claims yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($claims->hasPages())
            <div class="card-footer clearfix">{{ $claims->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
