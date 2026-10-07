<?php

use Illuminate\Support\Facades\Route;
use Modules\LogViewer\Http\Controllers\LogViewerController;

Route::middleware('admin')->prefix('admin/logs')->name('admin.logs.')->group(function () {
    Route::get('/', [LogViewerController::class, 'index'])->middleware('permission:view-logviewer')->name('index');
    Route::get('{file}', [LogViewerController::class, 'show'])->middleware('permission:view-logviewer')->name('show');
    Route::get('{file}/download', [LogViewerController::class, 'download'])->middleware('permission:download-logviewer')->name('download');
    Route::delete('{file}', [LogViewerController::class, 'destroy'])->middleware('permission:delete-logviewer')->name('destroy');
});
