<?php

namespace Modules\Api\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Api\Services\ApiTokenService;

/**
 * Signed-in app devices, so an administrator can cut off a lost phone or a former employee.
 */
class ApiTokenController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private readonly ApiTokenService $tokens) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = $filters['search'] ?? null;

        $tokens = PersonalAccessToken::query()
            ->with('tokenable')
            ->where('tokenable_type', (new User)->getMorphClass())
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "{$search}%")
                ->orWhereIn('tokenable_id', User::search($search)->select('id'))))
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('api::tokens', [
            'tokens' => $tokens,
            'activeCount' => PersonalAccessToken::where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
            'usedToday' => PersonalAccessToken::where('last_used_at', '>=', today())->count(),
        ]);
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
