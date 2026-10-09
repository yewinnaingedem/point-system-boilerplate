@if ($customer)
    <a href="{{ route('admin.loyalty.points.show', $customer) }}" class="font-weight-bold">{{ $customer->name }}</a>
    <div class="small text-muted">{{ $customer->email ?? $customer->phone }}</div>
@else
    <span class="text-muted">{{ __('Deleted customer') }}</span>
@endif
