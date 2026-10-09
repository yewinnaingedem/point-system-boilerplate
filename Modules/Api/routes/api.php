<?php

use Illuminate\Support\Facades\Route;
use Modules\Api\Http\Controllers\V1\AuthController;
use Modules\Api\Http\Controllers\V1\GatewayController;
use Modules\Api\Http\Controllers\V1\MeController;
use Modules\Api\Http\Controllers\V1\SettingController;
use Modules\Api\Http\Middleware\EnsureStaffToken;
use Modules\Api\Http\Middleware\EnsureTokenUserIsActive;
use Modules\Customer\Http\Controllers\Api\CustomerAuthController;
use Modules\Customer\Http\Controllers\Api\CustomerProfileController;
use Modules\Customer\Http\Middleware\EnsureCustomerToken;
use Modules\GiftCard\Http\Controllers\Api\CustomerGiftCardController;
use Modules\Merchant\Http\Controllers\Api\CustomerRewardController;
use Modules\Partner\Http\Controllers\PartnerPointController;
use Modules\Partner\Http\Middleware\AuthenticatePartner;

/*
 * Mounted at /api by the module's RouteServiceProvider (name prefix `api.`).
 *
 * Staff sign-in and customer SSO are the ONLY routes without a token (the gateway authenticates
 * each request by its signature instead). Every other route needs
 * a valid, unexpired token of the right kind: staff routes refuse customer tokens and the
 * customer routes refuse staff tokens; partner routes need the partner's server key. New POS endpoints go inside the staff group and are
 * gated with ->middleware('permission:<action>-<resource>') like the admin routes.
 */
Route::prefix('v1')->name('v1.')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:api-login')
        ->name('auth.login');

    // Signed gateway for other systems (KBZPay-style envelope; appid + SHA256 signature, see
    // GatewayKernel and docs/gateway-api.md). Rate-limited per client inside the kernel.
    Route::post('gateway', GatewayController::class)->name('gateway');

    // Staff (POS app): staff tokens only.
    Route::middleware(['auth:sanctum', EnsureStaffToken::class, EnsureTokenUserIsActive::class, 'throttle:api'])->group(function () {
        Route::post('auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');

        Route::get('me', MeController::class)->name('me');
        Route::get('settings', SettingController::class)->name('settings');
    });

    // Customers (customer app). Sign-in exchanges the partner project's JWT for our token;
    // everything else needs a customer token (Customer module, docs/customer-sso.md).
    Route::prefix('customer')->name('customer.')->group(function () {
        Route::post('auth/sso', [CustomerAuthController::class, 'sso'])
            ->middleware('throttle:customer-sso')
            ->name('auth.sso');

        Route::middleware(['auth:sanctum', EnsureCustomerToken::class, 'throttle:api'])->group(function () {
            Route::post('auth/logout', [CustomerAuthController::class, 'logout'])->name('auth.logout');
            Route::post('auth/logout-all', [CustomerAuthController::class, 'logoutAll'])->name('auth.logout-all');
            Route::get('me', [CustomerProfileController::class, 'me'])->name('me');
            Route::get('tier-history', [CustomerProfileController::class, 'tierHistory'])->name('tier-history');

            Route::get('merchants', [CustomerRewardController::class, 'merchants'])->name('merchants.index');
            Route::get('points', [CustomerRewardController::class, 'points'])->name('points');
            Route::get('redemptions', [CustomerRewardController::class, 'redemptions'])->name('redemptions.index');
            Route::post('redemptions', [CustomerRewardController::class, 'redeem'])->name('redemptions.store');

            Route::get('gift-cards', [CustomerGiftCardController::class, 'index'])->name('gift-cards.index');
            Route::post('gift-cards/{giftCard}/exchange', [CustomerGiftCardController::class, 'exchange'])->name('gift-cards.exchange');
            Route::post('gift-card-exchanges/{exchange}/verify', [CustomerGiftCardController::class, 'verify'])->middleware('throttle:customer-sso')->name('gift-card-exchanges.verify');
            Route::get('gift-card-exchanges', [CustomerGiftCardController::class, 'mine'])->name('gift-card-exchanges.index');
        });
    });

    // Partner project, server to server (Partner module): award points, read a customer's points.
    Route::prefix('partner')->name('partner.')->middleware([AuthenticatePartner::class, 'throttle:partner'])->group(function () {
        Route::post('points', [PartnerPointController::class, 'award'])->name('points.award');
        Route::get('customers/{externalId}/points', [PartnerPointController::class, 'show'])->name('customers.points');
    });
});
