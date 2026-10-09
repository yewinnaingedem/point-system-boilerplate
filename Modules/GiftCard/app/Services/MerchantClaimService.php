<?php

namespace Modules\GiftCard\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\GiftCard\Enums\ClaimStatus;
use Modules\GiftCard\Enums\ExchangeStatus;
use Modules\GiftCard\Exceptions\ClaimChanged;
use Modules\GiftCard\Models\GiftCardExchange;
use Modules\GiftCard\Models\MerchantClaim;
use Modules\Merchant\Models\Merchant;

/**
 * Merchants claim what they are owed for gift cards used at their branches; our staff pay.
 *
 *   claimable cards ──create──► Submitted ──pay──► Paid
 *   (used, no claim)              │  edit / delete      └─reject──► Rejected (cards released)
 *
 * A claim takes ALL the merchant's claimable cards used up to a day, so no card is left out or
 * claimed twice: each card points at its claim (claim_id) while the claim is open or paid. The
 * rows are locked while a claim is written. Reject and delete release the cards again.
 */
class MerchantClaimService
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /** Used gift cards of this merchant that are in no claim yet, used on or before $upTo. */
    public function claimable(Merchant|int $merchant, ?CarbonImmutable $upTo = null): Builder
    {
        return GiftCardExchange::query()
            ->where('merchant_id', $merchant instanceof Merchant ? $merchant->id : $merchant)
            ->where('status', ExchangeStatus::Used)
            ->whereNull('claim_id')
            ->when($upTo, fn (Builder $q) => $q->where('used_at', '<=', $upTo->endOfDay()));
    }

    /**
     * Claimable count and amount per merchant (for "ready to claim" and the merchant picker).
     *
     * @return Collection<int, object{merchant_id: int, cards: int, amount: string}>
     */
    public function claimableByMerchant(?int $merchantId = null): Collection
    {
        return GiftCardExchange::query()->toBase()
            ->where('status', ExchangeStatus::Used->value)->whereNull('claim_id')
            ->when($merchantId, fn ($q) => $q->where('merchant_id', $merchantId))
            ->groupBy('merchant_id')
            ->selectRaw('merchant_id, count(*) as cards, sum(payout_amount) as amount')
            ->get()
            ->keyBy('merchant_id');
    }

    /** @throws ClaimChanged when there is nothing to claim */
    public function create(Merchant $merchant, CarbonImmutable $upTo, ?string $note, User $actor): MerchantClaim
    {
        return $this->db->transaction(function () use ($merchant, $upTo, $note, $actor) {
            $claim = new MerchantClaim([
                'reference' => 'CLM-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'merchant_id' => $merchant->id,
                'status' => ClaimStatus::Submitted,
                'created_by' => $actor->id,
            ]);

            return $this->fill($claim, $upTo, $note);
        });
    }

    /** Change the day or the note of an open claim; its cards are taken again for the new day. */
    public function update(MerchantClaim $claim, CarbonImmutable $upTo, ?string $note): MerchantClaim
    {
        return $this->db->transaction(function () use ($claim, $upTo, $note) {
            $claim = $this->lockOpen($claim);
            GiftCardExchange::query()->where('claim_id', $claim->id)->update(['claim_id' => null]);

            return $this->fill($claim, $upTo, $note);
        });
    }

    /** Delete an open claim; its cards can be claimed again. */
    public function delete(MerchantClaim $claim): void
    {
        $this->db->transaction(function () use ($claim) {
            $claim = $this->lockOpen($claim);
            GiftCardExchange::query()->where('claim_id', $claim->id)->update(['claim_id' => null]);
            $claim->delete();
        });
    }

    /** Our staff paid the merchant. */
    public function pay(MerchantClaim $claim, CarbonImmutable $paidOn, ?string $paymentReference, User $actor): MerchantClaim
    {
        return $this->db->transaction(function () use ($claim, $paidOn, $paymentReference, $actor) {
            $claim = $this->lockOpen($claim);
            $claim->update([
                'status' => ClaimStatus::Paid,
                'paid_on' => $paidOn,
                'payment_reference' => $paymentReference,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);

            return $claim;
        });
    }

    /** Not paid; the cards are released so a corrected claim can include them. */
    public function reject(MerchantClaim $claim, string $reason, User $actor): MerchantClaim
    {
        return $this->db->transaction(function () use ($claim, $reason, $actor) {
            $claim = $this->lockOpen($claim);
            GiftCardExchange::query()->where('claim_id', $claim->id)->update(['claim_id' => null]);
            $claim->update([
                'status' => ClaimStatus::Rejected,
                'reject_reason' => $reason,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);

            return $claim;
        });
    }

    /** Take every claimable card up to the day into the claim and total it. Inside a transaction. */
    private function fill(MerchantClaim $claim, CarbonImmutable $upTo, ?string $note): MerchantClaim
    {
        $cards = $this->claimable($claim->merchant_id, $upTo)->lockForUpdate()->get(['id', 'payout_amount']);
        if ($cards->isEmpty()) {
            throw new ClaimChanged(__('No used gift cards to claim up to :date.', ['date' => $upTo->format(setting('date_format'))]));
        }

        $claim->fill([
            'up_to' => $upTo,
            'note' => $note,
            'cards_count' => $cards->count(),
            'amount' => $cards->reduce(fn (string $sum, GiftCardExchange $x) => bcadd($sum, (string) $x->payout_amount, 2), '0.00'),
        ])->save();
        GiftCardExchange::query()->whereKey($cards->modelKeys())->update(['claim_id' => $claim->id]);

        return $claim;
    }

    /** @throws ClaimChanged when it was paid, rejected or deleted meanwhile */
    private function lockOpen(MerchantClaim $claim): MerchantClaim
    {
        $locked = MerchantClaim::query()->whereKey($claim->id)->lockForUpdate()->first();
        if (! $locked?->isOpen()) {
            throw new ClaimChanged(__('This claim was already :status.', ['status' => strtolower(__($locked?->status->label() ?? 'deleted'))]));
        }

        return $locked;
    }
}
