<?php

use Illuminate\Support\Facades\Route;
use Modules\Loyalty\Http\Controllers\EarnedPointsController;
use Modules\Loyalty\Http\Controllers\LoyaltyTierController;
use Modules\Loyalty\Http\Controllers\PointActivityController;
use Modules\Loyalty\Http\Controllers\PointController;
use Modules\Loyalty\Http\Controllers\PointSummaryController;

Route::middleware('admin')->prefix('admin/loyalty')->name('admin.loyalty.')->group(function () {
    Route::get('tiers', [LoyaltyTierController::class, 'index'])->middleware('permission:view-loyaltytier')->name('tiers.index');
    Route::get('tiers/data', [LoyaltyTierController::class, 'data'])->middleware('permission:view-loyaltytier')->name('tiers.data');
    Route::get('tiers/{tier}/edit', [LoyaltyTierController::class, 'edit'])->middleware('permission:edit-loyaltytier')->name('tiers.edit');
    Route::put('tiers/{tier}', [LoyaltyTierController::class, 'update'])->middleware('permission:edit-loyaltytier')->name('tiers.update');

    Route::get('points', [PointController::class, 'index'])->middleware('permission:view-point')->name('points.index');
    Route::get('points/data', [PointController::class, 'data'])->middleware('permission:view-point')->name('points.data');
    Route::get('points/adjust', [PointController::class, 'create'])->middleware('permission:adjust-point')->name('points.create');
    Route::post('points/adjust', [PointController::class, 'store'])->middleware('permission:adjust-point')->name('points.store');
    // Own name prefix (points-earned.*), so the "Customer Points" item (points.*) doesn't light up here.
    Route::get('points/earned', [EarnedPointsController::class, 'index'])->middleware('permission:view-point')->name('points-earned.index');
    Route::get('points/earned/data', [EarnedPointsController::class, 'data'])->middleware('permission:view-point')->name('points-earned.data');
    Route::get('points/activity', [PointActivityController::class, 'index'])->middleware('permission:view-point')->name('points-activity.index');
    Route::get('points/activity/transactions', [PointActivityController::class, 'transactions'])->middleware('permission:view-point')->name('points-activity.transactions');
    Route::get('points/activity/earners', [PointActivityController::class, 'earners'])->middleware('permission:view-point')->name('points-activity.earners');
    Route::get('points/summary', [PointSummaryController::class, 'index'])->middleware('permission:view-point')->name('points-summary.index');
    Route::get('points/summary/months', [PointSummaryController::class, 'months'])->middleware('permission:view-point')->name('points-summary.months');
    Route::get('points/summary/expiring', [PointSummaryController::class, 'expiring'])->middleware('permission:view-point')->name('points-summary.expiring');
    Route::get('points/{customer}/lots', [PointController::class, 'lots'])->middleware('permission:view-point')->name('points.lots');
    Route::get('points/{customer}/months', [PointController::class, 'months'])->middleware('permission:view-point')->name('points.months');
    Route::get('points/{customer}', [PointController::class, 'show'])->middleware('permission:view-point')->name('points.show');
    Route::get('points/{customer}/history', [PointController::class, 'history'])->middleware('permission:view-point')->name('points.history');
});
