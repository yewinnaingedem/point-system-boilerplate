<?php

namespace Modules\Loyalty\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Customer\Models\Customer;
use Modules\Loyalty\Exceptions\InsufficientPoints;
use Modules\Loyalty\Http\Requests\AdjustPointsRequest;
use Modules\Loyalty\Http\Requests\PointTableRequest;
use Modules\Loyalty\Models\PointAccount;
use Modules\Loyalty\Services\PointWallet;
use Modules\Loyalty\Support\PointStatement;
use Modules\Loyalty\Tables\ExpiringPointsTable;
use Modules\Loyalty\Tables\PointAccountsTable;
use Modules\Loyalty\Tables\PointSummaryTable;
use Modules\Loyalty\Tables\PointTransactionsTable;

class PointController extends Controller
{
    public function __construct(private readonly PointWallet $wallet) {}

    public function index(): View
    {
        return view('loyalty::points.index', [
            'members' => PointAccount::query()->where('balance', '>', 0)->count(),
            'outstanding' => (int) PointAccount::query()->sum('balance'),
        ]);
    }

    public function data(PointTableRequest $request, PointAccountsTable $table): JsonResponse
    {
        return $table->response($request);
    }

    public function show(Customer $customer, PointStatement $statement): View
    {
        return view('loyalty::points.show', ['customer' => $customer, 'statement' => $statement->for($customer->id)]);
    }

    public function history(PointTableRequest $request, Customer $customer, PointTransactionsTable $table): JsonResponse
    {
        return $table->response($customer);
    }

    /** This customer's unspent points by expiry date. */
    public function lots(PointTableRequest $request, Customer $customer, ExpiringPointsTable $table): JsonResponse
    {
        return $table->response($customer);
    }

    /** This customer's month-by-month summary. */
    public function months(PointTableRequest $request, Customer $customer, PointSummaryTable $table): JsonResponse
    {
        return $table->response($customer->id);
    }

    public function create(Request $request): View
    {
        // Back from a failed save, or "Adjust points" from a customer's page: that customer is chosen.
        $id = $request->old('customer_id', $request->query('customer_id'));

        return view('loyalty::points.adjust', [
            'customer' => $id ? Customer::query()->with(['pointAccount', 'tierStatus'])->find((int) $id) : null,
        ]);
    }

    public function store(AdjustPointsRequest $request): RedirectResponse
    {
        $customer = $request->customer();
        $points = (int) $request->validated('points');

        try {
            $this->wallet->adjust($customer->id, $points, $request->validated('note'), $request->user()->id);
        } catch (InsufficientPoints $e) {
            return back()->withInput()->withErrors(['points' => __(':name only has :balance points.', ['name' => $customer->name, 'balance' => number_format($e->balance)])]);
        }

        return redirect()->route('admin.loyalty.points.show', $customer)
            ->with('success', __(':points points :direction :name.', [
                'points' => number_format(abs($points)),
                'direction' => $points > 0 ? __('added to') : __('removed from'),
                'name' => $customer->name,
            ]));
    }
}
