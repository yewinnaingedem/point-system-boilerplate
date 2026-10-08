<?php

namespace Modules\Customer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Customer\Http\Requests\CustomerTableRequest;
use Modules\Customer\Models\Customer;
use Modules\Customer\Services\CustomerSsoService;
use Modules\Customer\Support\CustomerTierSummary;
use Modules\Customer\Tables\CustomersTable;
use Modules\Customer\Tables\TierHistoryTable;
use Modules\Loyalty\Enums\TierLevel;
use Modules\Loyalty\Models\LoyaltyTier;
use Modules\Loyalty\Services\PointWallet;

/**
 * Customers are created and updated by sign-in from the partner project; here staff look them
 * up, see their tier history, and can deactivate them (which signs them out everywhere).
 */
class CustomerController extends Controller
{
    public function index(): View
    {
        return view('customer::customers.index', [
            'tiers' => TierLevel::cases(),
            'total' => Customer::query()->count(),
            'activeToday' => Customer::query()->where('last_login_at', '>=', today())->count(),
        ]);
    }

    public function data(CustomerTableRequest $request, CustomersTable $table): JsonResponse
    {
        return $table->response($request);
    }

    public function show(Customer $customer, CustomerTierSummary $summary, PointWallet $wallet): View
    {
        $tier = $summary->for($customer);

        return view('customer::customers.show', [
            'customer' => $customer,
            'tier' => $tier,
            'tierConfig' => $tier ? LoyaltyTier::query()->where('tier_level', $tier['level'])->first() : null,
            'points' => $wallet->balance($customer->id),
            'devices' => $customer->tokens()->latest('last_used_at')->get(['id', 'name', 'last_used_at', 'expires_at', 'created_at']),
        ]);
    }

    public function tierHistory(Customer $customer, TierHistoryTable $table): JsonResponse
    {
        return $table->response($customer);
    }

    public function toggleStatus(Customer $customer, CustomerSsoService $sso): RedirectResponse
    {
        $customer->update(['is_active' => ! $customer->is_active]);

        if (! $customer->is_active) {
            $sso->revokeAll($customer);

            return back()->with('success', __(':name deactivated and signed out of every device.', ['name' => $customer->name]));
        }

        return back()->with('success', __(':name can sign in again.', ['name' => $customer->name]));
    }

    public function revokeDevices(Customer $customer, CustomerSsoService $sso): RedirectResponse
    {
        $count = $sso->revokeAll($customer);

        return back()->with('success', __(':name signed out of :count devices.', ['name' => $customer->name, 'count' => $count]));
    }
}
