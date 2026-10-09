<?php

namespace Modules\GiftCard\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Modules\Customer\Models\Customer;
use Modules\GiftCard\Enums\ExchangeStatus;
use Modules\GiftCard\Exceptions\ExchangeRejected;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Models\GiftCardExchange;
use Modules\GiftCard\Notifications\GiftCardVerificationCode;
use Modules\Loyalty\Enums\PointTransactionType;
use Modules\Loyalty\Enums\TierLevel;
use Modules\Loyalty\Exceptions\InsufficientPoints;
use Modules\Loyalty\Models\PointTransaction;
use Modules\Loyalty\Services\PointWallet;
use Modules\Loyalty\Services\TierQualificationEngine;
use Modules\Merchant\Exceptions\RedemptionRejected;
use Modules\Merchant\Services\BranchCodeVerifier;

/**
 * Customers exchange points for gift cards.
 *
 * Checks, in order: card active → customer's tier ≥ the card's minimum → in stock → under the
 * per-customer limit → enough points. Cards with two-step verification first email a 6-digit
 * code (stored only as a hash) and take nothing until it is confirmed; then every check runs
 * again. Issuing happens in one transaction that locks the gift card row (SELECT ... FOR UPDATE),
 * so stock and limits hold under concurrent exchanges, and the points debit joins it.
 *
 * An issued card is then completed at a merchant branch (useAtBranch): shop staff type the
 * branch's 6-digit code on the customer's app, the card becomes `used`, and its value is owed
 * to that merchant. A card that belongs to a merchant works only at that merchant's branches.
 */
class GiftCardExchangeService
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly PointWallet $wallet,
        private readonly TierQualificationEngine $tiers,
        private readonly BranchCodeVerifier $branchCodes,
    ) {}

    /**
     * Can this customer exchange this card right now?
     *
     * @return array{available: bool, reason: ?string, message: ?string}
     */
    public function availability(Customer $customer, GiftCard $card): array
    {
        try {
            $this->check($customer, $card, $this->tierOf($customer), $this->wallet->balance($customer->id));

            return ['available' => true, 'reason' => null, 'message' => null];
        } catch (ExchangeRejected $e) {
            return ['available' => false, 'reason' => $e->reason, 'message' => $e->getMessage()];
        }
    }

    /**
     * Step 1. Without verification the card is issued at once; with it, a pending exchange is
     * created and the code is emailed.
     *
     * @throws ExchangeRejected
     */
    public function request(Customer $customer, GiftCard $card): GiftCardExchange
    {
        $this->check($customer, $card, $this->tierOf($customer), $this->wallet->balance($customer->id));

        if (! $card->requires_verification) {
            return $this->issue($customer, $card);
        }

        if (blank($customer->email)) {
            throw new ExchangeRejected(__('This gift card needs email verification, but no email address is on file.'), 'no_email');
        }

        // A new request replaces any earlier pending one for the same card.
        GiftCardExchange::query()->where('customer_id', $customer->id)->where('gift_card_id', $card->id)
            ->where('status', ExchangeStatus::Pending)->update(['status' => ExchangeStatus::Failed]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minutes = config('giftcard.verification_minutes');
        $exchange = GiftCardExchange::query()->create([
            'gift_card_id' => $card->id,
            'customer_id' => $customer->id,
            'status' => ExchangeStatus::Pending,
            'points' => $card->points_cost,
            'face_value' => $card->face_value,
            'verification_hash' => $this->hash($code),
            'verification_expires_at' => CarbonImmutable::now()->addMinutes($minutes),
        ]);

        $customer->notify(new GiftCardVerificationCode($code, $card->name, $card->points_cost, $minutes));

        return $exchange;
    }

    /**
     * Step 2 of a protected exchange: the emailed code. Right code → issued (all checks again).
     *
     * @throws ExchangeRejected
     */
    public function verify(Customer $customer, GiftCardExchange $exchange, string $code): GiftCardExchange
    {
        if ($exchange->customer_id !== $customer->id || $exchange->status !== ExchangeStatus::Pending) {
            throw new ExchangeRejected(__('There is no exchange waiting for a code.'), 'not_pending');
        }
        if ($exchange->verification_expires_at->isPast()) {
            $exchange->update(['status' => ExchangeStatus::Failed]);
            throw new ExchangeRejected(__('The code has expired. Start the exchange again.'), 'code_expired');
        }

        if (! hash_equals((string) $exchange->verification_hash, $this->hash($code))) {
            $exchange->increment('verification_attempts');
            $left = config('giftcard.verification_attempts') - $exchange->verification_attempts;
            if ($left <= 0) {
                $exchange->update(['status' => ExchangeStatus::Failed]);
                throw new ExchangeRejected(__('Too many wrong codes. Start the exchange again.'), 'too_many_attempts');
            }
            throw new ExchangeRejected(trans_choice('{1} Wrong code. 1 try left.|[2,*] Wrong code. :count tries left.', $left), 'wrong_code');
        }

        return $this->issue($customer, $exchange->giftCard, $exchange);
    }

    /**
     * Complete an issued gift card at a branch. The points were taken when it was issued; this
     * records where it was used and what is owed to the merchant (the card's value).
     *
     * Sending the same use again (an app retry after a timeout) returns it unchanged, as long as
     * it is the same branch.
     *
     * @throws ExchangeRejected not_issued, already_used, expired, branch_unavailable, wrong_shop, wrong_code, too_many_attempts
     */
    public function useAtBranch(Customer $customer, GiftCardExchange $exchange, int $branchId, string $code): GiftCardExchange
    {
        if ($exchange->customer_id !== $customer->id) {
            throw new ExchangeRejected(__('This gift card belongs to another customer.'), 'not_issued');
        }
        if ($exchange->status === ExchangeStatus::Used && $exchange->branch_id === $branchId) {
            return $exchange; // retry of the same use
        }
        $this->checkUsable($exchange);

        try {
            $branch = $this->branchCodes->availableBranch($branchId);
            // Before the code, so trying the wrong shop never uses up the customer's code attempts.
            $card = $exchange->giftCard()->with('merchant:id,name')->first();
            if (! $card->usableAtMerchant($branch->merchant_id)) {
                throw new ExchangeRejected(__('This gift card can only be used at :merchant.', ['merchant' => $card->merchant->name]), 'wrong_shop');
            }
            $this->branchCodes->verify($customer, $branch, $code);
        } catch (RedemptionRejected $e) {
            throw new ExchangeRejected($e->getMessage(), match (true) {
                $e->retryAfter !== null => 'too_many_attempts',
                $e->field === 'code' => 'wrong_code',
                default => 'branch_unavailable',
            });
        }

        return $this->db->transaction(function () use ($exchange, $branch) {
            $locked = GiftCardExchange::query()->whereKey($exchange->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === ExchangeStatus::Used && $locked->branch_id === $branch->id) {
                return $locked; // the same use arrived twice at once
            }
            $this->checkUsable($locked);

            $locked->update([
                'status' => ExchangeStatus::Used,
                'used_at' => now(),
                'merchant_id' => $branch->merchant_id,
                'branch_id' => $branch->id,
                'payout_amount' => $locked->face_value,
            ]);

            return $locked;
        });
    }

    /** @throws ExchangeRejected */
    private function checkUsable(GiftCardExchange $exchange): void
    {
        if ($exchange->status === ExchangeStatus::Used) {
            throw new ExchangeRejected(__('This gift card was already used.'), 'already_used');
        }
        if ($exchange->status !== ExchangeStatus::Issued) {
            throw new ExchangeRejected(__('This gift card can\'t be used (it is :status).', ['status' => __($exchange->status->label())]), 'not_issued');
        }
        if ($exchange->expires_at?->isPast()) {
            throw new ExchangeRejected(__('This gift card expired on :date.', ['date' => $exchange->expires_at->format(setting('date_format'))]), 'expired');
        }
    }

    /** Staff cancel an issued card: its points go back (original lots) and its stock is returned. */
    public function cancel(GiftCardExchange $exchange, User $actor, string $reason): GiftCardExchange
    {
        return $this->db->transaction(function () use ($exchange, $actor, $reason) {
            $locked = GiftCardExchange::query()->whereKey($exchange->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== ExchangeStatus::Issued) {
                // A used card can't be refunded: the shop already handed over the goods.
                throw new ExchangeRejected(__('Only issued gift cards that were not used can be cancelled.'), 'not_issued');
            }

            $debit = PointTransaction::query()->where('source_type', $locked->getMorphClass())->where('source_id', $locked->id)
                ->where('type', PointTransactionType::Redeem)->firstOrFail();
            $this->wallet->restore($debit, $locked, __('Gift card :code cancelled: :reason', ['code' => $locked->code, 'reason' => $reason]), $actor->id);

            $card = GiftCard::query()->whereKey($locked->gift_card_id)->lockForUpdate()->first();
            if ($card->stock !== null) {
                $card->increment('stock');
            }

            $locked->update(['status' => ExchangeStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by' => $actor->id, 'cancel_reason' => $reason]);

            return $locked;
        });
    }

    /** Lock the card, check again, take the points, count the stock down, give the code. */
    private function issue(Customer $customer, GiftCard $card, ?GiftCardExchange $pending = null): GiftCardExchange
    {
        $tier = $this->tierOf($customer);

        try {
            return $this->db->transaction(function () use ($customer, $card, $pending, $tier) {
                $locked = GiftCard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();
                $this->check($customer, $locked, $tier, null);

                $exchange = $pending ?? new GiftCardExchange(['gift_card_id' => $locked->id, 'customer_id' => $customer->id]);
                $now = CarbonImmutable::now();
                $exchange->fill([
                    'status' => ExchangeStatus::Issued,
                    'points' => $locked->points_cost,
                    'face_value' => $locked->face_value,
                    'code' => $this->newCode(),
                    'verification_hash' => null,
                    'issued_at' => $now,
                    'expires_at' => $locked->valid_days ? $now->addDays($locked->valid_days)->endOfDay() : null,
                ])->save();

                $this->wallet->debit($customer->id, $locked->points_cost, PointTransactionType::Redeem, $exchange,
                    __('Gift card :name (:code)', ['name' => $locked->name, 'code' => $exchange->code]));

                if ($locked->stock !== null) {
                    $locked->decrement('stock');
                }

                return $exchange;
            });
        } catch (InsufficientPoints $e) {
            throw new ExchangeRejected(__('Not enough points: you have :balance, this needs :cost.', [
                'balance' => number_format($e->balance), 'cost' => number_format($e->required),
            ]), 'not_enough_points');
        }
    }

    /**
     * @param  int|null  $balance  checked here when given; inside issue() the wallet checks it under its lock
     *
     * @throws ExchangeRejected
     */
    private function check(Customer $customer, GiftCard $card, ?TierLevel $tier, ?int $balance): void
    {
        if (! $card->is_active || ! $customer->is_active) {
            throw new ExchangeRejected(__('This gift card is not available.'), 'unavailable');
        }
        if (! $card->allowsTier($tier)) {
            throw new ExchangeRejected(__('This gift card is for :tier members and above.', ['tier' => $card->min_tier->label()]), 'tier');
        }
        if ($card->isOutOfStock()) {
            throw new ExchangeRejected(__('This gift card is out of stock.'), 'out_of_stock');
        }
        if ($card->per_customer_limit !== null) {
            $used = GiftCardExchange::query()->where('gift_card_id', $card->id)->where('customer_id', $customer->id)
                ->whereIn('status', ExchangeStatus::counted())->count();
            if ($used >= $card->per_customer_limit) {
                throw new ExchangeRejected(trans_choice('{1} You can get this gift card only once.|[2,*] You can get this gift card at most :count times.', $card->per_customer_limit), 'limit_reached');
            }
        }
        if ($balance !== null && $balance < $card->points_cost) {
            throw new ExchangeRejected(__('Not enough points: you have :balance, this needs :cost.', [
                'balance' => number_format($balance), 'cost' => number_format($card->points_cost),
            ]), 'not_enough_points');
        }
    }

    private function tierOf(Customer $customer): TierLevel
    {
        return $this->tiers->evaluateUserTierStatus($customer->id)?->tier ?? TierLevel::base();
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    /** GC-XXXX-XXXX-XXXX from an unambiguous alphabet; unique index is the final guard. */
    private function newCode(): string
    {
        do {
            $code = 'GC-'.implode('-', array_map(fn () => implode('', array_map(
                fn () => self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)], range(1, 4))), range(1, 3)));
        } while (GiftCardExchange::query()->where('code', $code)->exists());

        return $code;
    }
}
