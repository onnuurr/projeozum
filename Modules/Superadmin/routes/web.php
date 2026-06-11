<?php

use Illuminate\Support\Facades\Route;
use Modules\Superadmin\Http\Controllers\MenuController;
use Modules\Superadmin\Http\Controllers\PermissionController;
use Modules\Superadmin\Http\Controllers\RoleController;
use Modules\Superadmin\Http\Controllers\SettingsController;
use Modules\Superadmin\Http\Controllers\SuperadminController;
use Modules\Superadmin\Http\Controllers\SystemInfoController;

Route::middleware(['auth', 'verified', 'role:superadmin'])->group(function () {
    // Ayarlar
    Route::get('superadmin/settings', [SettingsController::class, 'index'])->name('superadmin.settings');
    Route::post('superadmin/settings', [SettingsController::class, 'update'])->name('superadmin.settings.update');

    // Canlı sistem bilgisi (JSON — dashboard ve ayarlar sayfası bunu polling eder)
    Route::get('superadmin/system-info', SystemInfoController::class)->name('superadmin.system-info');

    Route::resource('superadmin', SuperadminController::class)->names('superadmin');

    // Roller
    Route::post('superadmin/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::put('superadmin/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('superadmin/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    Route::get('superadmin/roles/{role}/permissions', [RoleController::class, 'permissions'])->name('roles.permissions');
    Route::post('superadmin/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('roles.permissions.sync');

    // İzinler
    Route::post('superadmin/permissions', [PermissionController::class, 'store'])->name('permissions.store');
    Route::put('superadmin/permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
    Route::delete('superadmin/permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');

    // Menüler
    Route::get('superadmin/menus', [MenuController::class, 'index'])->name('superadmin.menus');
    Route::post('superadmin/menus/reorder', [MenuController::class, 'reorder'])->name('superadmin.menus.reorder');
    Route::post('superadmin/menus', [MenuController::class, 'store'])->name('superadmin.menus.store');
    Route::put('superadmin/menus/{menu}', [MenuController::class, 'update'])->name('superadmin.menus.update');
    Route::delete('superadmin/menus/{menu}', [MenuController::class, 'destroy'])->name('superadmin.menus.destroy');
});
