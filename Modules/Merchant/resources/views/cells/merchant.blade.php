<a href="{{ route('admin.merchants.show', $merchant) }}" class="font-weight-bold">{{ $merchant->name }}</a>
<div class="small text-muted">{{ collect([$merchant->contact_person, $merchant->phone])->filter()->implode(' · ') ?: '—' }}</div>
