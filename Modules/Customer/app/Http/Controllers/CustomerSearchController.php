<?php

namespace Modules\Customer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Customer\Models\Customer;

/**
 * The customer picker (customer::partials.select): search by name, email or phone (prefix) or
 * the customer id, newest matches first, one page at a time. Answers in Select2's format.
 */
class CustomerSearchController extends Controller
{
    private const PAGE_SIZE = 20;

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);
        $term = trim($data['q'] ?? '');
        $page = (int) ($data['page'] ?? 1);

        $customers = Customer::query()
            ->with(['pointAccount:id,customer_id,balance', 'tierStatus:id,customer_id,current_tier'])
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->search($term)
                ->orWhere('external_id', 'like', "{$term}%")))
            ->orderByDesc('id')
            ->forPage($page, self::PAGE_SIZE)
            ->limit(self::PAGE_SIZE + 1)
            ->get(['id', 'external_id', 'name', 'email', 'phone', 'is_active']);

        return response()->json([
            'results' => $customers->take(self::PAGE_SIZE)->map(fn (Customer $c) => self::option($c))->values(),
            'pagination' => ['more' => $customers->count() > self::PAGE_SIZE],
        ]);
    }

    /**
     * One picker option; also used to show the already chosen customer when a form comes back.
     *
     * @return array{id: int, text: string, detail: string, points: int, tier: ?string, active: bool}
     */
    public static function option(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'text' => $customer->name,
            'detail' => collect([$customer->email, $customer->phone, $customer->external_id ? "#{$customer->external_id}" : null])->filter()->implode(' · '),
            'points' => (int) ($customer->pointAccount?->balance ?? 0),
            'tier' => $customer->tierStatus?->current_tier?->label(),
            'active' => (bool) $customer->is_active,
        ];
    }
}
