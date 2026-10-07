<?php

namespace Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Access\Services\ImpersonationService;

class ImpersonationController extends Controller
{
    public function __construct(private readonly ImpersonationService $impersonation) {}

    public function store(Request $request, User $user): RedirectResponse
    {
        $this->impersonation->start($request->session(), $request->user(), $user);

        return redirect()->route('admin.dashboard')
            ->with('success', __('You are now signed in as :name.', ['name' => $user->name]));
    }

    public function destroy(Request $request): RedirectResponse
    {
        if (! $this->impersonation->isImpersonating($request->session())) {
            return redirect()->route('admin.dashboard');
        }

        $borrowed = $request->user();
        $original = $this->impersonation->stop($request->session(), $borrowed);

        if ($original === null) {
            return redirect()->route('login');
        }

        return redirect()->route('admin.access.users.show', $borrowed)
            ->with('success', __('Welcome back, :name.', ['name' => $original->name]));
    }
}
