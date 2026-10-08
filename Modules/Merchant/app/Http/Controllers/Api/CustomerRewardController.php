<?php

namespace Modules\Merchant\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Modules\Loyalty\Models\PointTransaction;
use Modules\Loyalty\Support\PointStatement;
use Modules\Merchant\Exceptions\RedemptionRejected;
use Modules\Merchant\Http\Requests\Api\RedeemRequest;
use Modules\Merchant\Http\Resources\MerchantResource;
use Modules\Merchant\Http\Resources\RedemptionResource;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\Redemption;
use Modules\Merchant\Services\RedemptionService;

/**
 * Customer app: where points can be spent, the customer's balance, and redeeming at a branch.
 * Mounted under /api/v1/customer (customer tokens only, see EnsureCustomerToken).
 */
class CustomerRewardController extends Controller
{
    private const HISTORY_SIZE = 20;

    public function merchants(): AnonymousResourceCollection
    {
        $merchants = Merchant::query()->active()->orderBy('name')
            ->with([
                'branches' => fn (HasMany $q) => $q->active()->orderBy('name'),
                'rewards' => fn (HasMany $q) => $q->active()->orderBy('points_cost'),
            ])
            ->get();

        return MerchantResource::collection($merchants);
    }

    /** Balance, what expires when, month-by-month summary, and the latest changes. */
    public function points(Request $request, PointStatement $statement): JsonResponse
    {
        $customerId = $request->user()->id;

        return response()->json(['data' => [
            ...$statement->for($customerId),
            'history' => PointTransaction::query()->where('customer_id', $customerId)->latest('id')->limit(self::HISTORY_SIZE)->get()
                ->map(fn (PointTransaction $tx) => [
                    'type' => $tx->type->value,
                    'points' => $tx->points,
                    'balance_after' => $tx->balance_after,
                    'note' => $tx->note,
                    'at' => $tx->created_at->toIso8601String(),
                ]),
        ]]);
    }

    public function redemptions(Request $request): AnonymousResourceCollection
    {
        $redemptions = Redemption::query()->where('customer_id', $request->user()->id)
            ->with(['merchant:id,name', 'branch:id,name'])
            ->latest('redeemed_at')
            ->paginate(self::HISTORY_SIZE);

        return RedemptionResource::collection($redemptions);
    }

    public function redeem(RedeemRequest $request, RedemptionService $service): JsonResponse
    {
        try {
            $redemption = $service->redeem(
                $request->user(),
                (int) $request->validated('branch_id'),
                (int) $request->validated('reward_id'),
                $request->validated('code'),
                $request->validated('request_id'),
            );
        } catch (RedemptionRejected $e) {
            $error = ValidationException::withMessages([$e->field => $e->getMessage()]);

            throw $e->retryAfter !== null ? $error->status(429) : $error;
        }

        return (new RedemptionResource($redemption->load(['merchant:id,name', 'branch:id,name'])))
            ->response()
            ->setStatusCode($redemption->wasRecentlyCreated ? 201 : 200);
    }
}
