<?php

use Illuminate\Support\Facades\Route;
use Modules\Access\Http\Controllers\DeletedUserController;
use Modules\Access\Http\Controllers\ImpersonationController;
use Modules\Access\Http\Controllers\PermissionController;
use Modules\Access\Http\Controllers\RoleController;
use Modules\Access\Http\Controllers\UserController;
use Modules\Access\Http\Controllers\UserPasswordController;
use Modules\Access\Http\Controllers\UserSessionController;

Route::middleware('admin')->prefix('admin/access')->name('admin.access.')->group(function () {
    Route::get('users', [UserController::class, 'index'])->middleware('permission:view-user')->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->middleware('permission:create-user')->name('users.create');
    Route::post('users', [UserController::class, 'store'])->middleware('permission:create-user')->name('users.store');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:view-user')->name('users.show');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:edit-user')->name('users.edit');
    Route::get('users/{user}/password', [UserPasswordController::class, 'edit'])->middleware('permission:edit-user')->name('users.password.edit');
    Route::put('users/{user}/password', [UserPasswordController::class, 'update'])->middleware('permission:edit-user')->name('users.password.update');
    Route::delete('users/{user}/sessions', [UserSessionController::class, 'destroy'])->middleware('permission:edit-user')->name('users.sessions.destroy');
    Route::post('users/{user}/impersonate', [ImpersonationController::class, 'store'])->middleware('permission:impersonate-user')->name('users.impersonate');
    Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:edit-user')->name('users.update');
    Route::patch('users/{user}/status', [UserController::class, 'toggleStatus'])->middleware('permission:edit-user')->name('users.status');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:delete-user')->name('users.destroy');
    Route::patch('deleted-users/{deletedUser}/restore', [DeletedUserController::class, 'restore'])->middleware('permission:delete-user')->name('users.restore');
    Route::delete('deleted-users/{deletedUser}', [DeletedUserController::class, 'destroy'])->middleware('permission:delete-user')->name('users.force-delete');

    // No permission check: the borrowed account usually lacks impersonate-user. The service only
    // acts when the session really holds an impersonation.
    Route::post('impersonate/leave', [ImpersonationController::class, 'destroy'])->name('impersonate.leave');

    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:view-role')->name('roles.index');
    Route::get('roles/create', [RoleController::class, 'create'])->middleware('permission:create-role')->name('roles.create');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:create-role')->name('roles.store');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:edit-role')->name('roles.edit');
    Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:edit-role')->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:delete-role')->name('roles.destroy');

    Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:view-permission')->name('permissions.index');
});
