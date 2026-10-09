<?php

namespace Modules\Api\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A system allowed to call the signed gateway (e.g. the partner project's backend).
 *
 * @property int $id
 * @property string $name
 * @property string $app_id
 * @property string $secret
 * @property bool $is_active
 */
class ApiClient extends Model
{
    protected $fillable = ['name', 'app_id', 'secret', 'is_active', 'notes', 'secret_rotated_at'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'is_active' => 'boolean',
            'secret_rotated_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }
}
