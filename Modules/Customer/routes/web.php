<?php

use Illuminate\Support\Facades\Route;
use Modules\Customer\Http\Controllers\CustomerController;
use Modules\Customer\Http\Controllers\CustomerSearchController;

Route::middleware('admin')->prefix('admin/customers')->name('admin.customers.')->group(function () {
    Route::get('/', [CustomerController::class, 'index'])->middleware('permission:view-customer')->name('index');
    Route::get('data', [CustomerController::class, 'data'])->middleware('permission:view-customer')->name('data');
    // The customer picker on other screens (adjust points, exchange a gift card for a customer).
    Route::get('search', CustomerSearchController::class)
        ->middleware(['permission:view-customer|adjust-point|create-giftcardexchange', 'throttle:120,1'])->name('search');
    Route::get('{customer}', [CustomerController::class, 'show'])->middleware('permission:view-customer')->name('show');
    Route::get('{customer}/tier-history', [CustomerController::class, 'tierHistory'])->middleware('permission:view-customer')->name('tier-history');
    Route::patch('{customer}/status', [CustomerController::class, 'toggleStatus'])->middleware('permission:edit-customer')->name('status');
    Route::delete('{customer}/devices', [CustomerController::class, 'revokeDevices'])->middleware('permission:edit-customer')->name('devices.destroy');
});
