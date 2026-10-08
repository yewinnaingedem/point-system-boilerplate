@if ($customer)
    <div class="d-flex align-items-center">
        @include('customer::partials.avatar', ['customer' => $customer, 'size' => 32, 'class' => 'mr-2'])
        <div>
            <a href="{{ route('admin.loyalty.points.show', $customer) }}" class="font-weight-bold">{{ $customer->name }}</a>
            <div class="small text-muted">{{ $customer->email ?? $customer->phone }}</div>
        </div>
    </div>
@else
    <span class="text-muted">{{ __('Deleted customer') }}</span>
@endif
