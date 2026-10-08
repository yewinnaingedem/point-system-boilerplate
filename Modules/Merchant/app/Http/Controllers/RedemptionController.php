<?php

namespace Modules\Merchant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Merchant\Enums\RedemptionStatus;
use Modules\Merchant\Exceptions\RedemptionRejected;
use Modules\Merchant\Http\Requests\RedemptionTableRequest;
use Modules\Merchant\Http\Requests\ReverseRedemptionRequest;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\Redemption;
use Modules\Merchant\Services\RedemptionService;
use Modules\Merchant\Tables\RedemptionsTable;

class RedemptionController extends Controller
{
    public function index(): View
    {
        return view('merchant::redemptions.index', [
            'merchants' => Merchant::query()->orderBy('name')->pluck('name', 'id'),
            'statuses' => RedemptionStatus::cases(),
            'unsettledTotal' => (string) Redemption::query()->unsettled()->sum('payout_amount'),
            'todayCount' => Redemption::query()->where('redeemed_at', '>=', today())->count(),
        ]);
    }

    public function data(RedemptionTableRequest $request, RedemptionsTable $table): JsonResponse
    {
        return $table->response($request);
    }

    public function confirmReverse(Redemption $redemption): View|RedirectResponse
    {
        if (! $redemption->isReversible()) {
            return redirect()->route('admin.redemptions.index')->with('error', __('This redemption can no longer be reversed.'));
        }

        return view('merchant::redemptions.reverse', ['redemption' => $redemption->load(['customer', 'merchant', 'branch'])]);
    }

    public function reverse(ReverseRedemptionRequest $request, Redemption $redemption, RedemptionService $service): RedirectResponse
    {
        try {
            $service->reverse($redemption, $request->user(), $request->validated('reason'));
        } catch (RedemptionRejected $e) {
            return redirect()->route('admin.redemptions.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.redemptions.index')
            ->with('success', __(':reference reversed; :points points returned to the customer.', ['reference' => $redemption->reference, 'points' => number_format($redemption->points)]));
    }
}
