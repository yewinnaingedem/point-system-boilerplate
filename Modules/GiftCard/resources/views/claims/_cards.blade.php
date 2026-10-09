{{-- The gift cards in a claim (or a claim preview). --}}
<table class="table table-striped table-sm mb-0 text-nowrap">
    <thead>
        <tr>
            <th>{{ __('Used at') }}</th>
            <th>{{ __('Gift card code') }}</th>
            <th>{{ __('Gift card') }}</th>
            <th>{{ __('Branch') }}</th>
            <th>{{ __('Customer') }}</th>
            <th class="text-right">{{ __('Amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($cards as $card)
            <tr>
                <td>{{ $card->used_at->format(setting('date_format').' H:i') }}</td>
                <td><code>{{ $card->code }}</code></td>
                <td>{{ $card->giftCard->name }}</td>
                <td>{{ $card->branch?->name }}</td>
                <td>{{ $card->customer?->name }} <span class="text-muted small">{{ $card->customer?->external_id }}</span></td>
                <td class="text-right">{{ money($card->payout_amount, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">{{ $empty }}</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr class="font-weight-bold">
            <td colspan="5">{{ trans_choice('Total (:count gift card)|Total (:count gift cards)', $count, ['count' => number_format($count)]) }}</td>
            <td class="text-right">{{ money($total, 2) }}</td>
        </tr>
    </tfoot>
</table>
