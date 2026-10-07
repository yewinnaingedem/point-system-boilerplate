<?php

namespace Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Access\Support\PermissionMatrix;
use Spatie\Permission\Models\Permission;

/**
 * Read-only: permissions are defined by each module's seeder, not edited in the UI.
 */
class PermissionController extends Controller
{
    public function index(PermissionMatrix $matrix): View
    {
        return view('access::permissions.index', [
            'groups' => $matrix->group(Permission::with('roles:id,name')->orderBy('name')->get()),
        ]);
    }
}
