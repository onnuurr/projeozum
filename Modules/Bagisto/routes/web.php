<?php

use Illuminate\Support\Facades\Route;
use Modules\Bagisto\Http\Controllers\Admin\OrderReturnController;
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

// Siparişler › İadeler: bayi iade talepleri (karar, iade faturası kontrolü, para iadesi).
Route::middleware(['auth', 'verified'])
    ->prefix('order-returns')
    ->name('order-returns.')
    ->controller(OrderReturnController::class)
    ->group(function () {
        Route::middleware('can:order-return.view')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{orderReturn}', 'show')->name('show');
            Route::get('/{orderReturn}/invoice', 'invoice')->name('invoice');
        });

        Route::middleware('can:order-return.manage')->group(function () {
            Route::post('/{orderReturn}/approve', 'approve')->name('approve');
            Route::post('/{orderReturn}/reject', 'reject')->name('reject');
            Route::post('/{orderReturn}/invoice-review', 'reviewInvoice')->name('invoice-review');
        });

        Route::post('/{orderReturn}/refund', 'refund')
            ->middleware(['can:order-return.refund', 'throttle:10,1'])
            ->name('refund');
    });
