<?php

namespace Modules\Merchant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Merchant\Http\Requests\RewardRequest;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantReward;
use Modules\Merchant\Services\MerchantService;

class RewardController extends Controller
{
    public function __construct(private readonly MerchantService $merchants) {}

    public function create(Merchant $merchant): View
    {
        return view('merchant::rewards.form', ['merchant' => $merchant, 'reward' => new MerchantReward(['is_active' => true])]);
    }

    public function store(RewardRequest $request, Merchant $merchant): RedirectResponse
    {
        $reward = $this->merchants->saveReward($merchant, new MerchantReward, $request->validated());

        return redirect()->route('admin.merchants.show', $merchant)->with('success', __('Reward :name created.', ['name' => $reward->name]));
    }

    public function edit(MerchantReward $reward): View
    {
        return view('merchant::rewards.form', ['merchant' => $reward->merchant, 'reward' => $reward]);
    }

    public function update(RewardRequest $request, MerchantReward $reward): RedirectResponse
    {
        $this->merchants->saveReward($reward->merchant, $reward, $request->validated());

        return redirect()->route('admin.merchants.show', $reward->merchant_id)->with('success', __('Reward :name saved.', ['name' => $reward->name]));
    }

    public function destroy(MerchantReward $reward): RedirectResponse
    {
        $this->merchants->deleteReward($reward);

        return redirect()->route('admin.merchants.show', $reward->merchant_id)->with('success', __('Reward :name deleted.', ['name' => $reward->name]));
    }
}
