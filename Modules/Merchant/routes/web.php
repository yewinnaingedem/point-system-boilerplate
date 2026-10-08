<?php

use Illuminate\Support\Facades\Route;
use Modules\Merchant\Http\Controllers\BranchController;
use Modules\Merchant\Http\Controllers\MerchantController;
use Modules\Merchant\Http\Controllers\RedemptionController;
use Modules\Merchant\Http\Controllers\RewardController;

Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
    Route::prefix('merchants')->name('merchants.')->group(function () {
        Route::get('/', [MerchantController::class, 'index'])->middleware('permission:view-merchant')->name('index');
        Route::get('data', [MerchantController::class, 'data'])->middleware('permission:view-merchant')->name('data');
        Route::get('create', [MerchantController::class, 'create'])->middleware('permission:create-merchant')->name('create');
        Route::post('/', [MerchantController::class, 'store'])->middleware('permission:create-merchant')->name('store');

        // Branches and rewards (shallow: their own id identifies them once created).
        Route::get('branches/{branch}/edit', [BranchController::class, 'edit'])->middleware('permission:edit-merchant')->name('branches.edit');
        Route::put('branches/{branch}', [BranchController::class, 'update'])->middleware('permission:edit-merchant')->name('branches.update');
        Route::post('branches/{branch}/code', [BranchController::class, 'regenerateCode'])->middleware('permission:edit-merchantcode')->name('branches.code');
        Route::delete('branches/{branch}', [BranchController::class, 'destroy'])->middleware('permission:delete-merchant')->name('branches.destroy');
        Route::get('rewards/{reward}/edit', [RewardController::class, 'edit'])->middleware('permission:edit-merchant')->name('rewards.edit');
        Route::put('rewards/{reward}', [RewardController::class, 'update'])->middleware('permission:edit-merchant')->name('rewards.update');
        Route::delete('rewards/{reward}', [RewardController::class, 'destroy'])->middleware('permission:delete-merchant')->name('rewards.destroy');

        Route::get('{merchant}', [MerchantController::class, 'show'])->middleware('permission:view-merchant')->name('show');
        Route::get('{merchant}/edit', [MerchantController::class, 'edit'])->middleware('permission:edit-merchant')->name('edit');
        Route::put('{merchant}', [MerchantController::class, 'update'])->middleware('permission:edit-merchant')->name('update');
        Route::delete('{merchant}', [MerchantController::class, 'destroy'])->middleware('permission:delete-merchant')->name('destroy');
        Route::get('{merchant}/branches', [MerchantController::class, 'branches'])->middleware('permission:view-merchant')->name('branches.data');
        Route::get('{merchant}/branches/create', [BranchController::class, 'create'])->middleware('permission:create-merchant')->name('branches.create');
        Route::post('{merchant}/branches', [BranchController::class, 'store'])->middleware('permission:create-merchant')->name('branches.store');
        Route::get('{merchant}/rewards', [MerchantController::class, 'rewards'])->middleware('permission:view-merchant')->name('rewards.data');
        Route::get('{merchant}/rewards/create', [RewardController::class, 'create'])->middleware('permission:create-merchant')->name('rewards.create');
        Route::post('{merchant}/rewards', [RewardController::class, 'store'])->middleware('permission:create-merchant')->name('rewards.store');
    });

    Route::prefix('redemptions')->name('redemptions.')->group(function () {
        Route::get('/', [RedemptionController::class, 'index'])->middleware('permission:view-redemption')->name('index');
        Route::get('data', [RedemptionController::class, 'data'])->middleware('permission:view-redemption')->name('data');
        Route::get('{redemption}/reverse', [RedemptionController::class, 'confirmReverse'])->middleware('permission:reverse-redemption')->name('reverse.create');
        Route::post('{redemption}/reverse', [RedemptionController::class, 'reverse'])->middleware('permission:reverse-redemption')->name('reverse.store');
    });
});
