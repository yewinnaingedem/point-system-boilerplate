<?php

use Illuminate\Support\Facades\Route;
use Modules\AppSetting\Http\Controllers\AppSettingController;

Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
    Route::get('settings/{group?}', [AppSettingController::class, 'edit'])
        ->middleware('permission:view-appsetting')
        ->name('settings.edit');
    Route::put('settings/{group}', [AppSettingController::class, 'update'])
        ->middleware('permission:edit-appsetting')
        ->name('settings.update');
});
