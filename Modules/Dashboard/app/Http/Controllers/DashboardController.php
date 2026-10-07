<?php

namespace Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Dashboard\Services\DashboardStats;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStats $stats): View
    {
        $canSeeOverview = $request->user()->can('view-dashboard');

        return view('dashboard::index', [
            'canSeeOverview' => $canSeeOverview,
            'totals' => $canSeeOverview ? $stats->totals() : null,
            'recentLogins' => $canSeeOverview ? $stats->recentLogins() : collect(),
            'usersPerRole' => $canSeeOverview ? $stats->usersPerRole() : collect(),
            'modules' => $canSeeOverview ? $stats->enabledModules() : [],
        ]);
    }
}
