<?php

use Illuminate\Support\Facades\Route;
use Modules\GiftCard\Http\Controllers\ExchangeController;
use Modules\GiftCard\Http\Controllers\GiftCardController;

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
    Route::post('{exchange}/cancel', [ExchangeController::class, 'cancel'])->middleware('permission:cancel-giftcardexchange')->name('cancel');
});
