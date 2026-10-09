<?php

namespace Modules\GiftCard\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\GiftCard\Enums\ClaimStatus;
use Modules\GiftCard\Exceptions\ClaimChanged;
use Modules\GiftCard\Http\Requests\ClaimRequest;
use Modules\GiftCard\Http\Requests\DecideClaimRequest;
use Modules\GiftCard\Models\GiftCardExchange;
use Modules\GiftCard\Models\MerchantClaim;
use Modules\GiftCard\Services\MerchantClaimService;
use Modules\Merchant\Models\Merchant;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Merchants → Claims (CRUD): a merchant, or our staff for it, claims the gift cards used at its
 * branches; our staff pay or reject. A merchant's own staff only see their own claims.
 */
class ClaimController extends Controller
{
    private const PAGE_SIZE = 25;

    private const CARDS_PAGE_SIZE = 50;

    private const CARD_RELATIONS = ['giftCard:id,name', 'branch:id,name', 'customer:id,name,external_id'];

    public function __construct(private readonly MerchantClaimService $claims) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', array_column(ClaimStatus::cases(), 'value'))],
            'merchant_id' => ['nullable', 'integer'],
        ]);
        $merchantId = $user->merchant_id ?? ($filters['merchant_id'] ?? null);
        $visible = MerchantClaim::query()->visibleTo($user)->when($merchantId, fn ($q) => $q->where('merchant_id', $merchantId));
        $ready = $this->claims->claimableByMerchant($merchantId);

        return view('giftcard::claims.index', [
            'claims' => (clone $visible)->with(['merchant:id,name', 'creator:id,name'])
                ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
                ->latest('id')->paginate(self::PAGE_SIZE)->withQueryString(),
            'filters' => $filters,
            'merchants' => $user->isMerchantUser() ? collect() : Merchant::query()->orderBy('name')->pluck('name', 'id'),
            'readyAmount' => $ready->reduce(fn (string $sum, $row) => bcadd($sum, (string) $row->amount, 2), '0.00'),
            'readyCards' => (int) $ready->sum('cards'),
            'submittedAmount' => (string) (clone $visible)->where('status', ClaimStatus::Submitted)->sum('amount'),
            'paidThisMonth' => (string) (clone $visible)->where('status', ClaimStatus::Paid)->where('paid_on', '>=', today()->startOfMonth())->sum('amount'),
        ]);
    }

    /** The form, with a preview of the cards the claim will take for the chosen merchant and day. */
    public function create(Request $request): View
    {
        $user = $request->user();
        $merchantId = $user->merchant_id ?? $request->integer('merchant_id') ?: null;
        $upTo = $this->day($request->query('up_to'));

        return view('giftcard::claims.form', [
            'claim' => (new MerchantClaim(['merchant_id' => $merchantId, 'up_to' => $upTo]))
                ->setRelation('merchant', $merchantId ? Merchant::query()->find($merchantId, ['id', 'name']) : null),
            'merchants' => $user->isMerchantUser() ? collect() : Merchant::query()->orderBy('name')->pluck('name', 'id'),
            'ready' => $this->claims->claimableByMerchant($user->merchant_id),
            ...$this->preview($merchantId, $upTo),
        ]);
    }

    public function store(ClaimRequest $request): RedirectResponse
    {
        try {
            $claim = $this->claims->create(Merchant::query()->findOrFail($request->merchantId()), $request->upTo(), $request->validated('note'), $request->user());
        } catch (ClaimChanged $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.claims.show', $claim)->with('success', __('Claim :reference submitted: :count gift cards, :amount.', [
            'reference' => $claim->reference, 'count' => $claim->cards_count, 'amount' => money($claim->amount, 2),
        ]));
    }

    public function show(MerchantClaim $claim): View
    {
        return view('giftcard::claims.show', [
            'claim' => $claim->load(['merchant:id,name', 'creator:id,name', 'decider:id,name']),
            'cards' => $claim->exchanges()->with(self::CARD_RELATIONS)->orderBy('used_at')->orderBy('id')->paginate(self::CARDS_PAGE_SIZE),
        ]);
    }

    public function edit(Request $request, MerchantClaim $claim): View|RedirectResponse
    {
        if (! $claim->isOpen()) {
            return redirect()->route('admin.claims.show', $claim)->with('error', __('Only a submitted claim can be changed.'));
        }
        $upTo = $request->query('up_to') ? $this->day($request->query('up_to')) : $claim->up_to;

        return view('giftcard::claims.form', [
            'claim' => $claim->load('merchant:id,name')->fill(['up_to' => $upTo]),
            'merchants' => collect(),
            'ready' => collect(),
            ...$this->preview($claim->merchant_id, $upTo, $claim),
        ]);
    }

    public function update(ClaimRequest $request, MerchantClaim $claim): RedirectResponse
    {
        try {
            $this->claims->update($claim, $request->upTo(), $request->validated('note'));
        } catch (ClaimChanged $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.claims.show', $claim)->with('success', __('Claim :reference saved.', ['reference' => $claim->reference]));
    }

    public function destroy(MerchantClaim $claim): RedirectResponse
    {
        try {
            $this->claims->delete($claim);
        } catch (ClaimChanged $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.claims.index')->with('success', __('Claim :reference deleted. Its gift cards can be claimed again.', ['reference' => $claim->reference]));
    }

    public function pay(DecideClaimRequest $request, MerchantClaim $claim): RedirectResponse
    {
        return $this->decide($claim, fn () => $this->claims->pay($claim, CarbonImmutable::parse($request->validated('paid_on')), $request->validated('payment_reference'), $request->user()),
            __('Claim :reference marked as paid.', ['reference' => $claim->reference]));
    }

    public function reject(DecideClaimRequest $request, MerchantClaim $claim): RedirectResponse
    {
        return $this->decide($claim, fn () => $this->claims->reject($claim, $request->validated('reject_reason'), $request->user()),
            __('Claim :reference rejected. Its gift cards can be claimed again.', ['reference' => $claim->reference]));
    }

    /** The claim's cards as a CSV file for the merchant. */
    public function export(MerchantClaim $claim): StreamedResponse
    {
        return response()->streamDownload(function () use ($claim) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 mark, so Excel shows Myanmar names correctly
            fputcsv($out, ['Used at', 'Gift card code', 'Gift card', 'Branch', 'Customer', 'Customer id', 'Amount']);
            $claim->exchanges()->with(self::CARD_RELATIONS)->orderBy('id')->chunkById(500, function ($cards) use ($out) {
                foreach ($cards as $card) {
                    /** @var GiftCardExchange $card */
                    fputcsv($out, [$card->used_at->format('Y-m-d H:i'), $card->code, $card->giftCard->name, $card->branch?->name,
                        $card->customer?->name, $card->customer?->external_id, $card->payout_amount]);
                }
            });
            fclose($out);
        }, "{$claim->reference}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function decide(MerchantClaim $claim, callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (ClaimChanged $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.claims.show', $claim)->with('success', $message);
    }

    /**
     * The cards a claim for this merchant and day would take (an open claim's own cards count too).
     *
     * @return array<string, mixed>
     */
    private function preview(?int $merchantId, CarbonImmutable $upTo, ?MerchantClaim $claim = null): array
    {
        if ($merchantId === null) {
            return ['previewCards' => null, 'previewCount' => 0, 'previewAmount' => '0.00'];
        }
        $query = $this->claims->claimable($merchantId, $upTo)
            ->when($claim, fn ($q) => $q->orWhere(fn ($q) => $q->where('claim_id', $claim->id)->where('used_at', '<=', $upTo->endOfDay())));

        return [
            'previewCount' => (clone $query)->count(),
            'previewAmount' => (string) ((clone $query)->sum('payout_amount') ?: '0.00'),
            'previewCards' => $query->with(self::CARD_RELATIONS)->orderBy('used_at')->orderBy('id')->limit(self::CARDS_PAGE_SIZE)->get(),
        ];
    }

    private function day(mixed $value): CarbonImmutable
    {
        $day = is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? CarbonImmutable::parse($value) : CarbonImmutable::today();

        return $day->isFuture() ? CarbonImmutable::today() : $day;
    }
}
