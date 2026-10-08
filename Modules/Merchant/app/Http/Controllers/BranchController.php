<?php

namespace Modules\Merchant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Merchant\Exceptions\MerchantInUse;
use Modules\Merchant\Http\Requests\BranchRequest;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\MerchantBranch;
use Modules\Merchant\Services\MerchantService;

class BranchController extends Controller
{
    public function __construct(private readonly MerchantService $merchants) {}

    public function create(Merchant $merchant): View
    {
        return view('merchant::branches.form', ['merchant' => $merchant, 'branch' => new MerchantBranch(['is_active' => true])]);
    }

    public function store(BranchRequest $request, Merchant $merchant): RedirectResponse
    {
        $branch = $this->merchants->createBranch($merchant, $request->validated());

        return $this->backToMerchant($branch, $request->user()->can('view-merchantcode')
            ? __('Branch :name created. Its code is :code.', ['name' => $branch->name, 'code' => $branch->code])
            : __('Branch :name created.', ['name' => $branch->name]));
    }

    public function edit(MerchantBranch $branch): View
    {
        return view('merchant::branches.form', ['merchant' => $branch->merchant, 'branch' => $branch]);
    }

    public function update(BranchRequest $request, MerchantBranch $branch): RedirectResponse
    {
        $this->merchants->updateBranch($branch, $request->validated());

        return $this->backToMerchant($branch, __('Branch :name saved.', ['name' => $branch->name]));
    }

    public function regenerateCode(MerchantBranch $branch): RedirectResponse
    {
        $code = $this->merchants->regenerateCode($branch);

        return $this->backToMerchant($branch, __('New code for :name: :code. The old code no longer works.', ['name' => $branch->name, 'code' => $code]));
    }

    public function destroy(MerchantBranch $branch): RedirectResponse
    {
        try {
            $this->merchants->deleteBranch($branch);
        } catch (MerchantInUse $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->backToMerchant($branch, __('Branch :name deleted.', ['name' => $branch->name]));
    }

    private function backToMerchant(MerchantBranch $branch, string $message): RedirectResponse
    {
        return redirect()->route('admin.merchants.show', $branch->merchant_id)->with('success', $message);
    }
}
