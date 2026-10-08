<div class="d-flex align-items-center">
    @include('customer::partials.avatar', ['customer' => $customer, 'size' => 34, 'class' => 'mr-2'])
    <div>
        <a href="{{ route('admin.customers.show', $customer) }}" class="font-weight-bold">{{ $customer->name }}</a>
        <div class="small text-muted">{{ $customer->email ?: '—' }}</div>
    </div>
</div>
