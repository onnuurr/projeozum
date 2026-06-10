<?php

use Illuminate\Support\Facades\Route;
use Modules\Creative\Http\Controllers\BrandKitController;
use Modules\Creative\Http\Controllers\CreativeStudioController;
use Modules\Creative\Http\Controllers\CreativeTemplateController;

Route::middleware(['auth', 'verified', 'role:superadmin', 'can:creative.manage'])
    ->prefix('creative')->name('creative.')->group(function () {
    // ─── Stüdyo & üretim ─────────────────────────────────────────────────
    Route::get('/studio', [CreativeStudioController::class, 'index'])->name('studio');
    Route::post('/generate', [CreativeStudioController::class, 'generate'])->name('generate');
    Route::get('/gallery', [CreativeStudioController::class, 'gallery'])->name('gallery');
    Route::get('/export', [CreativeStudioController::class, 'export'])->name('export');

    // ─── Asset onay akışı ────────────────────────────────────────────────
    Route::post('/assets/{asset}/approve', [CreativeStudioController::class, 'approve'])
        ->whereNumber('asset')->name('assets.approve');
    Route::post('/assets/{asset}/reject', [CreativeStudioController::class, 'reject'])
        ->whereNumber('asset')->name('assets.reject');
    Route::post('/assets/{asset}/regenerate', [CreativeStudioController::class, 'regenerate'])
        ->whereNumber('asset')->name('assets.regenerate');
    Route::put('/assets/{asset}/caption', [CreativeStudioController::class, 'updateCaption'])
        ->whereNumber('asset')->name('assets.caption.update');

    // ─── Şablonlar ───────────────────────────────────────────────────────
    Route::get('/templates', [CreativeTemplateController::class, 'index'])->name('templates.index');
    Route::post('/templates', [CreativeTemplateController::class, 'store'])->name('templates.store');
    Route::put('/templates/{template}', [CreativeTemplateController::class, 'update'])
        ->whereNumber('template')->name('templates.update');
    Route::put('/templates/{template}/slots', [CreativeTemplateController::class, 'updateSlots'])
        ->whereNumber('template')->name('templates.slots');
    Route::delete('/templates/{template}', [CreativeTemplateController::class, 'destroy'])
        ->whereNumber('template')->name('templates.destroy');

    // ─── Marka Kiti (Brand Kit) ──────────────────────────────────────────
    Route::get('/brandkits', [BrandKitController::class, 'index'])->name('brandkits.index');
    Route::post('/brandkits', [BrandKitController::class, 'store'])->name('brandkits.store');
    Route::post('/brandkits/logo', [BrandKitController::class, 'uploadLogo'])->name('brandkits.logo');
    Route::post('/brandkits/font', [BrandKitController::class, 'uploadFont'])->name('brandkits.font');
    Route::put('/brandkits/{brandKit}', [BrandKitController::class, 'update'])
        ->whereNumber('brandKit')->name('brandkits.update');
    Route::delete('/brandkits/{brandKit}', [BrandKitController::class, 'destroy'])
        ->whereNumber('brandKit')->name('brandkits.destroy');
});
