<?php

namespace Modules\Api\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Permission;

/**
 * The signed-in user as the app sees them. Fields are listed one by one, so a new
 * column on `users` is never sent out by accident.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatarUrl(),
            'roles' => $this->getRoleNames()->values(),
            // Administrator passes every check, so the app gets the full list for it.
            'permissions' => $this->isAdministrator()
                ? Permission::orderBy('name')->pluck('name')
                : $this->getAllPermissions()->pluck('name')->sort()->values(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
