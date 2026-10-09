<?php

use Illuminate\Support\Facades\Route;
use Modules\Api\Http\Controllers\Admin\ApiClientController;

Route::middleware('admin')->prefix('admin/api-clients')->name('admin.api-clients.')->group(function () {
    Route::get('/', [ApiClientController::class, 'index'])->middleware('permission:view-apiclient')->name('index');
    Route::get('create', [ApiClientController::class, 'create'])->middleware('permission:create-apiclient')->name('create');
    Route::post('/', [ApiClientController::class, 'store'])->middleware('permission:create-apiclient')->name('store');
    Route::get('{client}/edit', [ApiClientController::class, 'edit'])->middleware('permission:edit-apiclient')->name('edit');
    Route::put('{client}', [ApiClientController::class, 'update'])->middleware('permission:edit-apiclient')->name('update');
    Route::post('{client}/rotate', [ApiClientController::class, 'rotate'])->middleware('permission:edit-apiclient')->name('rotate');
    Route::delete('{client}', [ApiClientController::class, 'destroy'])->middleware('permission:delete-apiclient')->name('destroy');
});
