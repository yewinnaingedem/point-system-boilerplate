<?php

namespace Modules\GiftCard\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\GiftCard\Enums\ExchangeStatus;
use Modules\GiftCard\Exceptions\ExchangeRejected;
use Modules\GiftCard\Http\Resources\ExchangeResource;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Models\GiftCardExchange;
use Modules\GiftCard\Services\GiftCardExchangeService;

/** Customer app: gift cards to choose from, exchange (with optional emailed code), own cards. */
class CustomerGiftCardController extends Controller
{
    public function __construct(private readonly GiftCardExchangeService $service) {}

    public function index(Request $request): JsonResponse
    {
        $customer = $request->user();

        return response()->json(['data' => GiftCard::query()->active()->orderBy('points_cost')->get()->map(fn (GiftCard $card) => [
            'id' => $card->id,
            'name' => $card->name,
            'description' => $card->description,
            'points' => $card->points_cost,
            'value' => $card->face_value,
            'min_tier' => $card->min_tier?->value,
            'in_stock' => ! $card->isOutOfStock(),
            'requires_verification' => $card->requires_verification,
            'valid_days' => $card->valid_days,
            ...$this->service->availability($customer, $card),
        ])]);
    }

    public function exchange(Request $request, GiftCard $giftCard): JsonResponse
    {
        $exchange = $this->attempt(fn () => $this->service->request($request->user(), $giftCard));

        return (new ExchangeResource($exchange->load('giftCard')))->response()
            ->setStatusCode($exchange->status === ExchangeStatus::Pending ? 202 : 201);
    }

    public function verify(Request $request, GiftCardExchange $exchange): ExchangeResource
    {
        $code = $request->validate(['code' => ['required', 'string', 'digits:6']])['code'];

        return new ExchangeResource($this->attempt(fn () => $this->service->verify($request->user(), $exchange, $code))->load('giftCard'));
    }

    public function mine(Request $request): AnonymousResourceCollection
    {
        return ExchangeResource::collection(GiftCardExchange::query()->with('giftCard')->where('customer_id', $request->user()->id)
            ->whereIn('status', [ExchangeStatus::Issued, ExchangeStatus::Cancelled])->latest('id')->paginate(20));
    }

    private function attempt(callable $action): GiftCardExchange
    {
        try {
            return $action();
        } catch (ExchangeRejected $e) {
            // 422 with a stable `reason` the app can switch on (tier, out_of_stock, limit_reached, ...).
            throw new HttpResponseException(response()->json([
                'message' => $e->getMessage(),
                'reason' => $e->reason,
                'errors' => ['gift_card' => [$e->getMessage()]],
            ], 422));
        }
    }
}
