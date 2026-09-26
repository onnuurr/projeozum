<?php

use Illuminate\Support\Facades\Route;
use Modules\Bagisto\Http\Controllers\Admin\StorefrontSettingsController;

// İş Ortakları › Bayi Portalı: mağaza (Bagisto) özellik anahtarları.
Route::middleware(['auth', 'verified', 'can:bagisto.manage'])
    ->prefix('bagisto/settings')
    ->name('bagisto.settings.')
    ->controller(StorefrontSettingsController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::put('/', 'update')->name('update');
        Route::post('/sync', 'sync')->name('sync');
    });

