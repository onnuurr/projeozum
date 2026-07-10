<?php

use Illuminate\Support\Facades\Route;
use Modules\Superadmin\Http\Controllers\LogAccessController;
use Modules\Superadmin\Http\Controllers\LogViewerController;
use Modules\Superadmin\Http\Controllers\MenuController;
use Modules\Superadmin\Http\Controllers\PermissionController;
use Modules\Superadmin\Http\Controllers\RoleController;
use Modules\Superadmin\Http\Controllers\SettingsController;
use Modules\Superadmin\Http\Controllers\SuperadminController;
use Modules\Superadmin\Http\Controllers\SystemInfoController;
use Modules\Superadmin\Http\Controllers\UserController;

// Granüler can:* izinleriyle korunur; izinler delege edilebilir (superadmin Gate::before ile geçer).
// İstisna: dashboard (superadmin resource) ve rol/izin yönetimi (rbac.manage) hassas meta-yönetim
// olduğu için EK olarak role:superadmin sert kilidini de taşır.
Route::middleware(['auth', 'verified'])->group(function () {
    // Ayarlar
    Route::get('superadmin/settings', [SettingsController::class, 'index'])
        ->middleware('can:settings.manage')->name('superadmin.settings');
    Route::post('superadmin/settings', [SettingsController::class, 'update'])
        ->middleware('can:settings.manage')->name('superadmin.settings.update');

    // Canlı sistem bilgisi (JSON — dashboard ve ayarlar sayfası bunu polling eder)
    Route::get('superadmin/system-info', SystemInfoController::class)
        ->middleware('can:settings.manage')->name('superadmin.system-info');

    // Menüler — Route::resource('superadmin')'dan ÖNCE tanımlanmalı, aksi halde
    // GET superadmin/menus, resource'un superadmin/{superadmin} (show) route'u
    // tarafından gölgelenir ve boş show() metoduna düşer (beyaz ekran).
    Route::get('superadmin/menus', [MenuController::class, 'index'])
        ->middleware('can:menu.manage')->name('superadmin.menus');
    Route::post('superadmin/menus/reorder', [MenuController::class, 'reorder'])
        ->middleware('can:menu.manage')->name('superadmin.menus.reorder');
    Route::post('superadmin/menus', [MenuController::class, 'store'])
        ->middleware('can:menu.manage')->name('superadmin.menus.store');
    Route::put('superadmin/menus/{menu}', [MenuController::class, 'update'])
        ->middleware('can:menu.manage')->name('superadmin.menus.update');
    Route::delete('superadmin/menus/{menu}', [MenuController::class, 'destroy'])
        ->middleware('can:menu.manage')->name('superadmin.menus.destroy');

    // Log erişim kapısı (unlock) — resource'dan ÖNCE tanımlanmalı, aksi hâlde
    // superadmin/{superadmin} (show) route'u "logs" ve "logs/unlock" segmentlerini
    // yakalar; SuperadminController::show('logs') çağrılır → boş 200 yanıtı.
    Route::get('superadmin/logs/unlock', [LogAccessController::class, 'showUnlock'])
        ->middleware('can:logs.view')->name('superadmin.logs.unlock');
    Route::post('superadmin/logs/unlock', [LogAccessController::class, 'unlock'])
        ->middleware('can:logs.view')->name('superadmin.logs.unlock.post');

    // Log görüntüleyici (logs.view izni + ayrı kapı şifresi gerektirir)
    Route::get('superadmin/logs', [LogViewerController::class, 'index'])
        ->middleware(['can:logs.view', 'log.access'])
        ->name('superadmin.logs');

    // Kullanıcı Yönetimi (hassas meta-yönetim) — users.* izni + role:superadmin çift kilit.
    // Bkz. Global Constraints: bu ekrandan superadmin dahil her role atama yapılabildiği için
    // delege edilmez. Route::resource('superadmin')'dan ÖNCE tanımlanmalı (shadow önleme).
    Route::middleware('role:superadmin')->group(function () {
        Route::get('superadmin/users', [UserController::class, 'index'])
            ->middleware('can:users.view')->name('superadmin.users.index');
        Route::post('superadmin/users', [UserController::class, 'store'])
            ->middleware('can:users.manage')->name('superadmin.users.store');
        Route::put('superadmin/users/{user}', [UserController::class, 'update'])
            ->middleware('can:users.manage')->name('superadmin.users.update');
        Route::delete('superadmin/users/{user}', [UserController::class, 'destroy'])
            ->middleware('can:users.manage')->name('superadmin.users.destroy');
        Route::post('superadmin/users/{user}/toggle', [UserController::class, 'toggleActive'])
            ->middleware('can:users.manage')->name('superadmin.users.toggle');
    });

    // Dashboard (meta-yönetim) — yalnızca superadmin.
    Route::middleware('role:superadmin')->group(function () {
        Route::resource('superadmin', SuperadminController::class)->names('superadmin');
    });

    // Roller & İzinler (hassas meta-RBAC) — rbac.manage izni + role:superadmin çift kilit.
    Route::middleware(['can:rbac.manage', 'role:superadmin'])->group(function () {
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
    });
});
