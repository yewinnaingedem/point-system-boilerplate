<?php

namespace Modules\Loyalty\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Loyalty\Http\Requests\TierTableRequest;
use Modules\Loyalty\Http\Requests\UpdateTierRequest;
use Modules\Loyalty\Models\LoyaltyTier;
use Modules\Loyalty\Services\TierConfigService;
use Modules\Loyalty\Tables\LoyaltyTiersTable;

class LoyaltyTierController extends Controller
{
    public function __construct(private readonly TierConfigService $config) {}

    /** The page: "How tiers work" and an empty table; rows come from data(). */
    public function index(): View
    {
        return view('loyalty::tiers.index', ['cycleMonths' => $this->config->cycleMonths()]);
    }

    /** JSON rows for the tiers table (DataTables). */
    public function data(TierTableRequest $request, LoyaltyTiersTable $table): JsonResponse
    {
        return $table->response();
    }

    public function edit(LoyaltyTier $tier): View
    {
        return view('loyalty::tiers.edit', ['tier' => $tier]);
    }

    public function update(UpdateTierRequest $request, LoyaltyTier $tier): RedirectResponse
    {
        $this->config->updateTier($tier, $request->validated());

        return redirect()
            ->route('admin.loyalty.tiers.index')
            ->with('success', __(':tier tier saved.', ['tier' => $tier->tier_level->label()]));
    }
}
