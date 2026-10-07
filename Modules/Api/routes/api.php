<?php

use Illuminate\Support\Facades\Route;
use Modules\Api\Http\Controllers\V1\AuthController;
use Modules\Api\Http\Controllers\V1\MeController;
use Modules\Api\Http\Controllers\V1\SettingController;
use Modules\Api\Http\Middleware\EnsureTokenUserIsActive;

/*
 * Mounted at /api by the module's RouteServiceProvider (name prefix `api.`).
 *
 * Sign-in is the ONLY route without a token. Every other route needs a valid, unexpired
 * token of an active user. New POS endpoints go inside the authenticated group and are
 * gated with ->middleware('permission:<action>-<resource>') like the admin routes.
 */
Route::prefix('v1')->name('v1.')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:api-login')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', EnsureTokenUserIsActive::class, 'throttle:api'])->group(function () {
        Route::post('auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');

        Route::get('me', MeController::class)->name('me');
        Route::get('settings', SettingController::class)->name('settings');
    });
});
