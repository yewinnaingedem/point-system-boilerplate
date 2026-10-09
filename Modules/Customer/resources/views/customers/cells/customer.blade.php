<a href="{{ route('admin.customers.show', $customer) }}" class="font-weight-bold">{{ $customer->name }}</a>
<div class="small text-muted">{{ $customer->email ?: '—' }}</div>
