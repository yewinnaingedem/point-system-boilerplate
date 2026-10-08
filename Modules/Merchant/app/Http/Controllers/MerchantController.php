<?php

namespace Modules\Merchant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Merchant\Exceptions\MerchantInUse;
use Modules\Merchant\Http\Requests\MerchantRequest;
use Modules\Merchant\Http\Requests\MerchantTableRequest;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\Redemption;
use Modules\Merchant\Services\MerchantService;
use Modules\Merchant\Tables\BranchesTable;
use Modules\Merchant\Tables\MerchantsTable;
use Modules\Merchant\Tables\RewardsTable;

class MerchantController extends Controller
{
    public function __construct(private readonly MerchantService $merchants) {}

    public function index(): View
    {
        return view('merchant::merchants.index');
    }

    public function data(MerchantTableRequest $request, MerchantsTable $table): JsonResponse
    {
        return $table->response($request);
    }

    public function create(): View
    {
        return view('merchant::merchants.form', ['merchant' => new Merchant(['is_active' => true, 'settlement_rate' => 0])]);
    }

    public function store(MerchantRequest $request): RedirectResponse
    {
        $merchant = $this->merchants->saveMerchant(new Merchant, $request->validated());

        return redirect()->route('admin.merchants.show', $merchant)
            ->with('success', __('Merchant :name created. Add its branches and rewards next.', ['name' => $merchant->name]));
    }

    public function show(Merchant $merchant): View
    {
        $unsettled = Redemption::query()->unsettled()->where('merchant_id', $merchant->id);

        return view('merchant::merchants.show', [
            'merchant' => $merchant,
            'unsettledTotal' => (string) (clone $unsettled)->sum('payout_amount'),
            'unsettledCount' => (clone $unsettled)->count(),
        ]);
    }

    public function edit(Merchant $merchant): View
    {
        return view('merchant::merchants.form', ['merchant' => $merchant]);
    }

    public function update(MerchantRequest $request, Merchant $merchant): RedirectResponse
    {
        $this->merchants->saveMerchant($merchant, $request->validated());

        return redirect()->route('admin.merchants.show', $merchant)->with('success', __('Merchant :name saved.', ['name' => $merchant->name]));
    }

    public function destroy(Merchant $merchant): RedirectResponse
    {
        try {
            $this->merchants->deleteMerchant($merchant);
        } catch (MerchantInUse $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.merchants.index')->with('success', __('Merchant :name deleted.', ['name' => $merchant->name]));
    }

    public function branches(MerchantTableRequest $request, Merchant $merchant, BranchesTable $table): JsonResponse
    {
        return $table->response($request, $merchant);
    }

    public function rewards(MerchantTableRequest $request, Merchant $merchant, RewardsTable $table): JsonResponse
    {
        return $table->response($request, $merchant);
    }
}
