<?php

namespace Modules\GiftCard\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Customer\Models\Customer;
use Modules\GiftCard\Enums\ExchangeStatus;
use Modules\GiftCard\Exceptions\ExchangeRejected;
use Modules\GiftCard\Http\Requests\CancelExchangeRequest;
use Modules\GiftCard\Http\Requests\ExchangeForCustomerRequest;
use Modules\GiftCard\Http\Requests\GiftCardTableRequest;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Models\GiftCardExchange;
use Modules\GiftCard\Services\GiftCardExchangeService;
use Modules\GiftCard\Tables\ExchangesTable;

/**
 * Gift Cards → Exchanges: the list, one exchange's page (with cancel), and exchanging a card
 * for a customer at the counter (same checks as the app; two-step cards still email the code).
 */
class ExchangeController extends Controller
{
    public function __construct(private readonly GiftCardExchangeService $service) {}

    public function index(): View
    {
        return view('giftcard::exchanges.index', [
            'cards' => GiftCard::query()->orderBy('name')->pluck('name', 'id'),
            'statuses' => ExchangeStatus::cases(),
            'issuedToday' => GiftCardExchange::query()->whereIn('status', ExchangeStatus::counted())->where('issued_at', '>=', today())->count(),
            'usedToday' => GiftCardExchange::query()->where('status', ExchangeStatus::Used)->where('used_at', '>=', today())->count(),
            'pending' => GiftCardExchange::query()->where('status', ExchangeStatus::Pending)->where('verification_expires_at', '>', now())->count(),
        ]);
    }

    public function data(GiftCardTableRequest $request, ExchangesTable $table): JsonResponse
    {
        return $table->response($request);
    }

    public function show(GiftCardExchange $exchange): View
    {
        return view('giftcard::exchanges.show', [
            'exchange' => $exchange->load(['giftCard.merchant:id,name', 'customer', 'merchant:id,name', 'branch:id,name', 'claim:id,reference,status', 'canceller:id,name']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('giftcard::exchanges.create', [
            'cards' => GiftCard::query()->active()->with('merchant:id,name')->orderBy('points_cost')->get(),
            'selected' => $request->integer('gift_card_id') ?: null,
            'customer' => ($id = $request->old('customer_id', $request->query('customer_id')))
                ? Customer::query()->with(['pointAccount', 'tierStatus'])->find((int) $id) : null,
        ]);
    }

    public function store(ExchangeForCustomerRequest $request): RedirectResponse
    {
        $card = GiftCard::query()->findOrFail($request->validated('gift_card_id'));
        try {
            $exchange = $this->service->request($request->customer(), $card);
        } catch (ExchangeRejected $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.gift-card-exchanges.show', $exchange)->with('success', $exchange->status === ExchangeStatus::Pending
            ? __('A 6-digit code was emailed to :name. Enter it below to issue the gift card.', ['name' => $request->customer()->name])
            : __('Gift card :code issued to :name.', ['code' => $exchange->code, 'name' => $request->customer()->name]));
    }

    /** Two-step card exchanged at the counter: the customer reads out the emailed code. */
    public function verify(Request $request, GiftCardExchange $exchange): RedirectResponse
    {
        $code = $request->validate(['code' => ['required', 'string', 'digits:6']])['code'];
        try {
            $exchange = $this->service->verify($exchange->customer, $exchange, $code);
        } catch (ExchangeRejected $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.gift-card-exchanges.show', $exchange)->with('success', __('Gift card :code issued.', ['code' => $exchange->code]));
    }

    public function cancel(CancelExchangeRequest $request, GiftCardExchange $exchange): RedirectResponse
    {
        try {
            $this->service->cancel($exchange, $request->user(), $request->validated('reason'));
        } catch (ExchangeRejected $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.gift-card-exchanges.show', $exchange)
            ->with('success', __('Gift card :code cancelled; :points points returned.', ['code' => $exchange->code, 'points' => number_format($exchange->points)]));
    }
}
