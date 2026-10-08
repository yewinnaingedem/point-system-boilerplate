<?php

namespace Modules\Api\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Api\Http\Requests\ApiTokenTableRequest;
use Modules\Api\Services\ApiTokenService;
use Modules\Api\Tables\ApiTokensTable;

/**
 * Signed-in app devices, so an administrator can cut off a lost phone or a former employee.
 */
class ApiTokenController extends Controller
{
    public function __construct(private readonly ApiTokenService $tokens) {}

    /** The page: counters and an empty table; rows come from data() via DataTables. */
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);

        return view('api::tokens', [
            'search' => $filters['search'] ?? null,
            'activeCount' => PersonalAccessToken::where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
            'usedToday' => PersonalAccessToken::where('last_used_at', '>=', today())->count(),
        ]);
    }

    /** JSON rows for the devices table (server-side DataTables). */
    public function data(ApiTokenTableRequest $request, ApiTokensTable $table): JsonResponse
    {
        return $table->response($request);
    }

    public function destroy(PersonalAccessToken $token): RedirectResponse
    {
        $this->tokens->revoke($token);

        return back()->with('success', __('Device ":name" signed out.', ['name' => $token->name]));
    }

    public function destroyForUser(User $user): RedirectResponse
    {
        $count = $this->tokens->revokeAll($user);

        return back()->with('success', __(':name signed out of :count devices.', ['name' => $user->name, 'count' => $count]));
    }
}
