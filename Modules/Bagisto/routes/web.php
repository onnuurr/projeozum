<?php

use Illuminate\Support\Facades\Route;
use Modules\Bagisto\Http\Controllers\Admin\DealerAgreementController;
use Modules\Bagisto\Http\Controllers\Admin\DealerMailTemplateController;
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

// Bayi E-postaları: başvuru (tenant-application-mail.manage) ve iade (order-return-mail.manage)
// e-postalarının metinleri. Grup yetkileri controller'da denetlenir (en az biri gerekir).
Route::middleware(['auth', 'verified'])
    ->prefix('dealer-mail-templates')
    ->name('dealer-mail-templates.')
    ->controller(DealerMailTemplateController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::put('/', 'update')->name('update');
        Route::post('/preview', 'preview')->name('preview');
        Route::post('/test', 'test')->middleware('throttle:10,1')->name('test');
    });

// İş Ortakları › Bayi Sözleşmeleri: bayi portalında onaylatılan sözleşme/onay metinleri (sürümlü).
Route::middleware(['auth', 'verified', 'can:dealer-agreement.manage'])
    ->prefix('dealer-agreements')
    ->name('dealer-agreements.')
    ->controller(DealerAgreementController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/preview', 'preview')->middleware('throttle:60,1')->name('preview');
        Route::put('/settings', 'updateSettings')->name('settings');
        Route::post('/{agreement}/publish', 'publish')->whereNumber('agreement')->name('publish');
        Route::put('/{agreement}/draft', 'saveDraft')->whereNumber('agreement')->name('draft.save');
        Route::delete('/{agreement}/draft', 'discardDraft')->whereNumber('agreement')->name('draft.discard');
        Route::post('/{agreement}/annexes', 'createAnnex')->whereNumber('agreement')->name('annexes.store');
        Route::delete('/{agreement}/annex', 'retireAnnex')->whereNumber('agreement')->name('annexes.retire');
    });

// Bayi sözleşmesi onay arşivi: başvuru detayındaki onaylı kopyalar + yazdırılabilir çıktı (başvuru yetkisi).
Route::middleware(['auth', 'verified', 'can:tenant-application.approve'])
    ->prefix('dealer-agreements/acceptances')
    ->name('dealer-agreements.acceptances.')
    ->controller(DealerAgreementController::class)
    ->group(function () {
        Route::get('/', 'acceptances')->name('index');
        Route::get('/{acceptance}/print', 'printAcceptance')->whereNumber('acceptance')->name('print');
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
