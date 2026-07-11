<?php

use Illuminate\Support\Facades\Route;
use Modules\Creative\Http\Controllers\BrandKitController;
use Modules\Creative\Http\Controllers\CreativeStudioController;
use Modules\Creative\Http\Controllers\CreativeTemplateController;
use Modules\Creative\Http\Controllers\MannequinController;
use Modules\Creative\Http\Controllers\PoseController;
use Modules\Creative\Http\Controllers\RejectionReasonController;
use Modules\Creative\Http\Controllers\ReviewChatController;
use Modules\Creative\Http\Controllers\TryonController;

// Yetkiler hibrit granülerdir (bkz. CreativePermissionSeeder): okuma için creative.view,
// yazma işlemleri için alt-özellik bazlı can:* izinleri. Sert role:superadmin kilidi YOK —
// izinler tenant/diğer rollere delege edilebilir; superadmin Gate::before ile hepsini geçer.
Route::middleware(['auth', 'verified'])
    ->prefix('creative')->name('creative.')->group(function () {
    // ─── Stüdyo & üretim ─────────────────────────────────────────────────
    Route::get('/studio', [CreativeStudioController::class, 'index'])
        ->middleware('can:creative.view')->name('studio');
    Route::get('/gallery', [CreativeStudioController::class, 'gallery'])
        ->middleware('can:creative.view')->name('gallery');
    Route::get('/export', [CreativeStudioController::class, 'export'])
        ->middleware('can:creative.view')->name('export');
    Route::post('/generate', [CreativeStudioController::class, 'generate'])
        ->middleware('can:creative.generate')->name('generate');

    // ─── Asset onay akışı ────────────────────────────────────────────────
    Route::post('/assets/{asset}/approve', [CreativeStudioController::class, 'approve'])
        ->middleware('can:creative.approve')->whereNumber('asset')->name('assets.approve');
    Route::post('/assets/{asset}/reject', [CreativeStudioController::class, 'reject'])
        ->middleware('can:creative.approve')->whereNumber('asset')->name('assets.reject');
    Route::post('/assets/{asset}/regenerate', [CreativeStudioController::class, 'regenerate'])
        ->middleware('can:creative.generate')->whereNumber('asset')->name('assets.regenerate');
    Route::put('/assets/{asset}/caption', [CreativeStudioController::class, 'updateCaption'])
        ->middleware('can:creative.generate')->whereNumber('asset')->name('assets.caption.update');

    // ─── Şablonlar ───────────────────────────────────────────────────────
    Route::get('/templates', [CreativeTemplateController::class, 'index'])
        ->middleware('can:creative.view')->name('templates.index');
    Route::post('/templates', [CreativeTemplateController::class, 'store'])
        ->middleware('can:creative.template.manage')->name('templates.store');
    Route::put('/templates/{template}', [CreativeTemplateController::class, 'update'])
        ->middleware('can:creative.template.manage')->whereNumber('template')->name('templates.update');
    Route::put('/templates/{template}/slots', [CreativeTemplateController::class, 'updateSlots'])
        ->middleware('can:creative.template.manage')->whereNumber('template')->name('templates.slots');
    Route::delete('/templates/{template}', [CreativeTemplateController::class, 'destroy'])
        ->middleware('can:creative.template.manage')->whereNumber('template')->name('templates.destroy');

    // ─── Sanal Manken ────────────────────────────────────────────────────
    Route::get('/mannequins', [MannequinController::class, 'index'])
        ->middleware('can:creative.view')->name('mannequins.index');
    Route::post('/mannequins', [MannequinController::class, 'store'])
        ->middleware('can:creative.asset.manage')->name('mannequins.store');
    Route::post('/mannequins/{mannequin}/regenerate', [MannequinController::class, 'regenerate'])
        ->middleware('can:creative.asset.manage')->whereNumber('mannequin')->name('mannequins.regenerate');
    Route::post('/mannequins/{mannequin}/approve', [MannequinController::class, 'approve'])
        ->middleware('can:creative.approve')->whereNumber('mannequin')->name('mannequins.approve');
    Route::post('/mannequins/{mannequin}/reject', [MannequinController::class, 'reject'])
        ->middleware('can:creative.approve')->whereNumber('mannequin')->name('mannequins.reject');
    Route::delete('/mannequins/{mannequin}', [MannequinController::class, 'destroy'])
        ->middleware('can:creative.asset.manage')->whereNumber('mannequin')->name('mannequins.destroy');

    // ─── Bağımsız Poz Kütüphanesi ────────────────────────────────────────
    Route::get('/poses', [PoseController::class, 'index'])
        ->middleware('can:creative.view')->name('poses.index');
    Route::post('/poses/generate', [PoseController::class, 'generateAll'])
        ->middleware('can:creative.asset.manage')->name('poses.generate');
    Route::post('/poses', [PoseController::class, 'store'])
        ->middleware('can:creative.asset.manage')->name('poses.store');
    Route::post('/poses/{pose}/regenerate', [PoseController::class, 'regenerate'])
        ->middleware('can:creative.asset.manage')->whereNumber('pose')->name('poses.regenerate');
    Route::delete('/poses/{pose}', [PoseController::class, 'destroy'])
        ->middleware('can:creative.asset.manage')->whereNumber('pose')->name('poses.destroy');

    // ─── Ürün Giydirme (try-on → product_images) ─────────────────────────
    Route::get('/tryon', [TryonController::class, 'index'])
        ->middleware('can:creative.view')->name('tryon.index');
    Route::post('/tryon', [TryonController::class, 'store'])
        ->middleware('can:creative.asset.manage')->name('tryon.store');
    Route::post('/tryon/{result}/approve', [TryonController::class, 'approve'])
        ->middleware('can:creative.approve')->whereNumber('result')->name('tryon.approve');
    Route::post('/tryon/{result}/reject', [TryonController::class, 'reject'])
        ->middleware('can:creative.approve')->whereNumber('result')->name('tryon.reject');
    Route::post('/tryon/{result}/cover', [TryonController::class, 'setCover'])
        ->middleware('can:creative.asset.manage')->whereNumber('result')->name('tryon.cover');
    Route::delete('/tryon/{result}', [TryonController::class, 'destroyResult'])
        ->middleware('can:creative.asset.manage')->whereNumber('result')->name('tryon.destroy');

    // ─── Onay sohbet asistanı (reddedilen manken/tryon için) ──────────────
    Route::get('/mannequins/{mannequin}/review-chat', [ReviewChatController::class, 'showMannequin'])
        ->middleware('can:creative.view')->whereNumber('mannequin')->name('mannequins.review-chat.show');
    Route::get('/tryon/{result}/review-chat', [ReviewChatController::class, 'showTryon'])
        ->middleware('can:creative.view')->whereNumber('result')->name('tryon.review-chat.show');
    Route::post('/mannequins/{mannequin}/review-chat', [ReviewChatController::class, 'sendMannequin'])
        ->middleware('can:creative.view')->whereNumber('mannequin')->name('mannequins.review-chat');
    Route::post('/mannequins/{mannequin}/review-chat/apply', [ReviewChatController::class, 'applyMannequin'])
        ->middleware('can:creative.view')->whereNumber('mannequin')->name('mannequins.review-chat.apply');
    Route::post('/tryon/{result}/review-chat', [ReviewChatController::class, 'sendTryon'])
        ->middleware('can:creative.view')->whereNumber('result')->name('tryon.review-chat');
    Route::post('/tryon/{result}/review-chat/apply', [ReviewChatController::class, 'applyTryon'])
        ->middleware('can:creative.view')->whereNumber('result')->name('tryon.review-chat.apply');

    // ─── Ret Seçim Maddeleri (superadmin yönetir) ────────────────────────
    // Reddetme diyaloğundaki "düzeltilmesi gereken alan" maddeleri. Yalnız
    // creative.rejection-reasons.manage izni (superadmin) yönetebilir.
    Route::get('/rejection-reasons', [RejectionReasonController::class, 'index'])
        ->middleware('can:creative.rejection-reasons.manage')->name('rejection-reasons.index');
    Route::post('/rejection-reasons', [RejectionReasonController::class, 'store'])
        ->middleware('can:creative.rejection-reasons.manage')->name('rejection-reasons.store');
    Route::put('/rejection-reasons/{rejectionReason}', [RejectionReasonController::class, 'update'])
        ->middleware('can:creative.rejection-reasons.manage')->whereNumber('rejectionReason')->name('rejection-reasons.update');
    Route::delete('/rejection-reasons/{rejectionReason}', [RejectionReasonController::class, 'destroy'])
        ->middleware('can:creative.rejection-reasons.manage')->whereNumber('rejectionReason')->name('rejection-reasons.destroy');

    // ─── Marka Kiti (Brand Kit) ──────────────────────────────────────────
    Route::get('/brandkits', [BrandKitController::class, 'index'])
        ->middleware('can:creative.view')->name('brandkits.index');
    Route::post('/brandkits', [BrandKitController::class, 'store'])
        ->middleware('can:creative.brandkit.manage')->name('brandkits.store');
    Route::post('/brandkits/logo', [BrandKitController::class, 'uploadLogo'])
        ->middleware('can:creative.brandkit.manage')->name('brandkits.logo');
    Route::post('/brandkits/font', [BrandKitController::class, 'uploadFont'])
        ->middleware('can:creative.brandkit.manage')->name('brandkits.font');
    Route::put('/brandkits/{brandKit}', [BrandKitController::class, 'update'])
        ->middleware('can:creative.brandkit.manage')->whereNumber('brandKit')->name('brandkits.update');
    Route::delete('/brandkits/{brandKit}', [BrandKitController::class, 'destroy'])
        ->middleware('can:creative.brandkit.manage')->whereNumber('brandKit')->name('brandkits.destroy');
});
