<?php

namespace Modules\Partner\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Customer\Models\Customer;
use Modules\Customer\Support\CustomerTierSummary;
use Modules\Loyalty\Support\PointStatement;
use Modules\Partner\Exceptions\PointAwardConflict;
use Modules\Partner\Http\Requests\AwardPointsRequest;
use Modules\Partner\Services\PointAwardService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Partner project → POS: award points, and look up a customer's points (for its own screens).
 */
class PartnerPointController extends Controller
{
    public function award(AwardPointsRequest $request, PointAwardService $awards, PointStatement $statement): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $awards->award($data['customer'], (int) $data['points'], $data['reference'], $data['note'] ?? null,
                isset($data['spent_amount']) ? (string) $data['spent_amount'] : null);
        } catch (PointAwardConflict $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        $lot = $awards->lotOf($result['transaction']);

        return response()->json(['data' => [
            'reference' => $result['transaction']->reference,
            'points' => $result['transaction']->points,
            'expires_on' => $lot?->expires_at?->subSecond()->toDateString(),
            'awarded_at' => $result['transaction']->created_at->toIso8601String(),
            'replayed' => $result['replayed'],
            'customer' => ['external_id' => $result['customer']->external_id, 'balance' => $statement->for($result['customer']->id)['balance']],
        ]], $result['replayed'] ? Response::HTTP_OK : Response::HTTP_CREATED);
    }

    public function show(string $externalId, PointStatement $statement, CustomerTierSummary $tiers): JsonResponse
    {
        $customer = Customer::query()->where('external_id', $externalId)->first();
        if ($customer === null) {
            return response()->json(['message' => __('No customer with this id.')], Response::HTTP_NOT_FOUND);
        }

        $tier = $tiers->for($customer);

        return response()->json(['data' => [
            'external_id' => $customer->external_id,
            'active' => $customer->is_active,
            'tier' => $tier ? ['level' => $tier['level']->value, 'label' => $tier['label'], 'cycle_spent' => $tier['cycle_spent']] : null,
            ...$statement->for($customer->id),
        ]]);
    }
}
