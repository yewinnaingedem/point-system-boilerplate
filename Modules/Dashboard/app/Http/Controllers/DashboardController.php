<?php

namespace Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Dashboard\Services\DashboardStats;
use Modules\Dashboard\Support\ModuleCards;

/** Landing page for everyone: a welcome, a card per module the user may open, recent sign-ins. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStats $stats, ModuleCards $cards): View
    {
        $user = $request->user();
        $canSeeOverview = $user->can('view-dashboard');

        return view('dashboard::index', [
            'user' => $user,
            'greeting' => $this->greeting((int) now()->format('G')),
            'role' => $user->roles->pluck('name')->first(),
            'merchantName' => $user->merchant_id ? $stats->merchantName($user->merchant_id) : null,
            'sections' => $cards->for($user),
            'canSeeOverview' => $canSeeOverview,
            'recentLogins' => $canSeeOverview ? $stats->recentLogins() : collect(),
        ]);
    }

    private function greeting(int $hour): string
    {
        return match (true) {
            $hour < 12 => __('Good morning'),
            $hour < 17 => __('Good afternoon'),
            default => __('Good evening'),
        };
    }
}
