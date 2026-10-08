<?php

namespace Modules\GiftCard\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\GiftCard\Enums\ExchangeStatus;
use Modules\GiftCard\Exceptions\ExchangeRejected;
use Modules\GiftCard\Http\Requests\CancelExchangeRequest;
use Modules\GiftCard\Http\Requests\GiftCardTableRequest;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Models\GiftCardExchange;
use Modules\GiftCard\Services\GiftCardExchangeService;
use Modules\GiftCard\Tables\ExchangesTable;

class ExchangeController extends Controller
{
    public function index(): View
    {
        return view('giftcard::exchanges.index', [
            'cards' => GiftCard::query()->orderBy('name')->pluck('name', 'id'),
            'statuses' => ExchangeStatus::cases(),
            'issuedToday' => GiftCardExchange::query()->where('status', ExchangeStatus::Issued)->where('issued_at', '>=', today())->count(),
            'pending' => GiftCardExchange::query()->where('status', ExchangeStatus::Pending)->where('verification_expires_at', '>', now())->count(),
        ]);
    }

    public function data(GiftCardTableRequest $request, ExchangesTable $table): JsonResponse
    {
        return $table->response($request);
    }

    public function cancel(CancelExchangeRequest $request, GiftCardExchange $exchange, GiftCardExchangeService $service): RedirectResponse
    {
        try {
            $service->cancel($exchange, $request->user(), $request->validated('reason'));
        } catch (ExchangeRejected $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Gift card :code cancelled; :points points returned.', ['code' => $exchange->code, 'points' => number_format($exchange->points)]));
    }
}
