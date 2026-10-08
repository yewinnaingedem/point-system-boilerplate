<div class="font-weight-bold">{{ $branch->name }}</div>
<div class="small text-muted">{{ collect([$branch->address, $branch->phone])->filter()->implode(' · ') ?: '—' }}</div>
