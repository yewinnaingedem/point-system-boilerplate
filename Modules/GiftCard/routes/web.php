<?php

use Illuminate\Support\Facades\Route;
use Modules\GiftCard\Http\Controllers\ClaimController;
use Modules\GiftCard\Http\Controllers\ExchangeController;
use Modules\GiftCard\Http\Controllers\GiftCardController;
use Modules\Merchant\Http\Middleware\LimitToOwnMerchant;

Route::middleware('admin')->prefix('admin/gift-cards')->name('admin.gift-cards.')->group(function () {
    Route::get('/', [GiftCardController::class, 'index'])->middleware('permission:view-giftcard')->name('index');
    Route::get('data', [GiftCardController::class, 'data'])->middleware('permission:view-giftcard')->name('data');
    Route::get('create', [GiftCardController::class, 'create'])->middleware('permission:create-giftcard')->name('create');
    Route::post('/', [GiftCardController::class, 'store'])->middleware('permission:create-giftcard')->name('store');
    Route::get('{giftCard}/edit', [GiftCardController::class, 'edit'])->middleware('permission:edit-giftcard')->name('edit');
    Route::put('{giftCard}', [GiftCardController::class, 'update'])->middleware('permission:edit-giftcard')->name('update');
    Route::delete('{giftCard}', [GiftCardController::class, 'destroy'])->middleware('permission:delete-giftcard')->name('destroy');
});

// Own name prefix, so the sidebar's "Gift Cards" item (admin.gift-cards.*) doesn't light up here.
Route::middleware('admin')->prefix('admin/gift-cards/exchanges')->name('admin.gift-card-exchanges.')->group(function () {
    Route::get('/', [ExchangeController::class, 'index'])->middleware('permission:view-giftcardexchange')->name('index');
    Route::get('data', [ExchangeController::class, 'data'])->middleware('permission:view-giftcardexchange')->name('data');
    Route::get('create', [ExchangeController::class, 'create'])->middleware('permission:create-giftcardexchange')->name('create');
    Route::post('/', [ExchangeController::class, 'store'])->middleware('permission:create-giftcardexchange')->name('store');
    Route::get('{exchange}', [ExchangeController::class, 'show'])->middleware('permission:view-giftcardexchange')->name('show');
    Route::post('{exchange}/verify', [ExchangeController::class, 'verify'])->middleware(['permission:create-giftcardexchange', 'throttle:10,1'])->name('verify');
    Route::post('{exchange}/cancel', [ExchangeController::class, 'cancel'])->middleware('permission:cancel-giftcardexchange')->name('cancel');
});

// Merchants → Claims (CRUD). LimitToOwnMerchant: a merchant's own staff see only their claims and can't pay or reject.
Route::middleware(['admin', LimitToOwnMerchant::class])->prefix('admin/claims')->name('admin.claims.')->group(function () {
    Route::get('/', [ClaimController::class, 'index'])->middleware('permission:view-merchantclaim')->name('index');
    Route::get('create', [ClaimController::class, 'create'])->middleware('permission:create-merchantclaim')->name('create');
    Route::post('/', [ClaimController::class, 'store'])->middleware('permission:create-merchantclaim')->name('store');
    Route::get('{claim}', [ClaimController::class, 'show'])->middleware('permission:view-merchantclaim')->name('show');
    Route::get('{claim}/export', [ClaimController::class, 'export'])->middleware('permission:view-merchantclaim')->name('export');
    Route::get('{claim}/edit', [ClaimController::class, 'edit'])->middleware('permission:edit-merchantclaim')->name('edit');
    Route::put('{claim}', [ClaimController::class, 'update'])->middleware('permission:edit-merchantclaim')->name('update');
    Route::delete('{claim}', [ClaimController::class, 'destroy'])->middleware('permission:delete-merchantclaim')->name('destroy');
    Route::post('{claim}/pay', [ClaimController::class, 'pay'])->middleware('permission:settle-merchantclaim')->name('pay');
    Route::post('{claim}/reject', [ClaimController::class, 'reject'])->middleware('permission:settle-merchantclaim')->name('reject');
});
