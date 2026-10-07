<?php

use Illuminate\Support\Facades\Route;
use Modules\Api\Http\Controllers\Admin\ApiTokenController;

Route::middleware('admin')->prefix('admin/api-tokens')->name('admin.api-tokens.')->group(function () {
    Route::get('/', [ApiTokenController::class, 'index'])->middleware('permission:view-apitoken')->name('index');
    Route::delete('{token}', [ApiTokenController::class, 'destroy'])->middleware('permission:delete-apitoken')->name('destroy');
    Route::delete('user/{user}', [ApiTokenController::class, 'destroyForUser'])->middleware('permission:delete-apitoken')->name('destroy-user');
});
