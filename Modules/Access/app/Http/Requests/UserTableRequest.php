<?php

namespace Modules\Access\Http\Requests;

use App\Http\Requests\DataTableRequest;

class UserTableRequest extends DataTableRequest
{
    /** Tabs on the users list. "deleted" lists soft-deleted users (needs delete-user). */
    public const STATUSES = ['active', 'inactive', 'deleted'];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->can('view-user')
            && ($this->input('status') !== 'deleted' || $user->can('delete-user'));
    }

    protected function filterRules(): array
    {
        return [
            'status' => ['nullable', 'in:'.implode(',', self::STATUSES)],
            'role' => ['nullable', 'string', 'exists:roles,name'],
        ];
    }

    public function status(): ?string
    {
        return $this->validated('status');
    }

    public function role(): ?string
    {
        return $this->validated('role');
    }
}
