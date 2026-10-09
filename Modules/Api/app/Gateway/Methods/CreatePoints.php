<?php

namespace Modules\Api\Gateway\Methods;

use Modules\Api\Gateway\GatewayCustomers;
use Modules\Api\Gateway\GatewayError;
use Modules\Api\Gateway\GatewayMethod;
use Modules\Api\Models\ApiClient;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Support\PointStatement;
use Modules\Partner\Exceptions\PointAwardConflict;
use Modules\Partner\Services\PointAwardService;
use Symfony\Component\HttpFoundation\Response;

/**
 * pos.point.create: award points for something that happened on the caller's side (an order).
 *
 * Idempotent by `reference`: the same award again answers with the first one (`replayed`: true);
 * the same reference for another customer or amount is REFERENCE_CONFLICT. Unknown customers are
 * created when `name` is sent; without it the customer must already exist. Optional `spent_amount`
 * also counts towards the tier, in the same transaction.
 */
class CreatePoints implements GatewayMethod
{
    public function __construct(
        private readonly GatewayCustomers $customers,
        private readonly PointAwardService $awards,
        private readonly PointStatement $statement,
    ) {}

    public function rules(): array
    {
        return [
            'external_id' => GatewayCustomers::EXTERNAL_ID,
            'name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'string', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'points' => ['required', 'integer', 'min:1', 'max:'.config('partner.max_points_per_award')],
            'reference' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
            'spent_amount' => ['nullable', 'numeric', 'gt:0', 'max:9999999999999', 'decimal:0,2'],
        ];
    }

    public function handle(array $input, ApiClient $client): array
    {
        $existing = Customer::query()->where('external_id', $input['external_id'])->first();
        if ($existing && ! $existing->is_active) {
            throw new GatewayError('CUSTOMER_INACTIVE', __('This customer is deactivated and can\'t receive points.'), Response::HTTP_FORBIDDEN);
        }
        if ($existing === null && blank($input['name'] ?? null)) {
            throw GatewayError::notFound('CUSTOMER_NOT_FOUND', __('No customer with this external_id. Send name (and email / phone) to create them.'));
        }

        try {
            $result = $this->awards->award(
                $this->customers->profile($input, $existing),
                (int) $input['points'],
                $input['reference'],
                $input['note'] ?? null,
                isset($input['spent_amount']) ? (string) $input['spent_amount'] : null,
            );
        } catch (PointAwardConflict $e) {
            throw new GatewayError('REFERENCE_CONFLICT', $e->getMessage(), Response::HTTP_CONFLICT);
        }

        $transaction = $result['transaction'];

        return [
            'reference' => $transaction->reference,
            'points' => $transaction->points,
            'expires_on' => $this->awards->lotOf($transaction)?->expires_at?->subSecond()->toDateString(),
            'awarded_at' => $transaction->created_at->toIso8601String(),
            'replayed' => $result['replayed'],
            'customer' => [
                'external_id' => $result['customer']->external_id,
                'balance' => $this->statement->for($result['customer']->id)['balance'],
            ],
        ];
    }
}
