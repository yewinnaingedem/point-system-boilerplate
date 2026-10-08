<?php

namespace Modules\Api\Tables;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Api\Http\Requests\ApiTokenTableRequest;
use Yajra\DataTables\DataTables;

/**
 * Server-side data for the signed-in app devices list. Never sends the token hash or
 * abilities: only the listed, rendered columns leave the server.
 */
class ApiTokensTable
{
    private const ORDERABLE = ['id', 'name', 'created_at', 'last_used_at', 'expires_at'];

    private const COLUMNS = ['owner', 'device', 'signed_in', 'last_used', 'expires', 'actions'];

    public function __construct(private readonly DataTables $datatables) {}

    public function response(ApiTokenTableRequest $request): JsonResponse
    {
        $search = $request->searchTerm();

        $query = PersonalAccessToken::query()
            ->with('tokenable')
            ->where('tokenable_type', (new User)->getMorphClass());

        return $this->datatables->eloquent($query)
            // Prefix search on the device name or the owner (name, email, phone).
            ->filter(fn (Builder $q) => $q->when($search, fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('name', 'like', "{$search}%")
                ->orWhereIn('tokenable_id', User::search($search)->select('id')))))
            ->whitelist(self::ORDERABLE)
            ->addColumn('owner', fn (PersonalAccessToken $token) => view('api::cells.owner', ['owner' => $token->tokenable])->render())
            ->addColumn('device', fn (PersonalAccessToken $token) => view('api::cells.device', ['token' => $token])->render())
            ->addColumn('signed_in', fn (PersonalAccessToken $token) => $token->created_at->diffForHumans())
            ->addColumn('last_used', fn (PersonalAccessToken $token) => $token->last_used_at?->diffForHumans() ?? __('Never'))
            ->addColumn('expires', fn (PersonalAccessToken $token) => view('api::cells.expires', ['token' => $token])->render())
            ->addColumn('actions', fn (PersonalAccessToken $token) => view('api::cells.actions', ['token' => $token, 'owner' => $token->tokenable])->render())
            ->rawColumns(['owner', 'device', 'expires', 'actions'])
            ->only(self::COLUMNS)
            ->toJson();
    }
}
