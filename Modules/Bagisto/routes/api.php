<?php

use Illuminate\Support\Facades\Route;
use Modules\Bagisto\Http\Controllers\Api\OrderWebhookController;
use Modules\Bagisto\Http\Controllers\Api\ReturnInvoiceApiController;
use Modules\Bagisto\Http\Controllers\Api\TenantAccountApiController;
use Modules\Bagisto\Http\Controllers\Api\TenantApplicationApiController;
use Modules\Bagisto\Http\Middleware\VerifyBagistoInboundToken;

// Bagisto'nun `PushEventToSaas` job'ının hedefi (order.created / order.cancelled).
// Auth: Bearer token (VerifyBagistoInboundToken) — HMAC değil.
Route::post('webhooks/bagisto', [OrderWebhookController::class, 'handle'])
    ->middleware([VerifyBagistoInboundToken::class, 'throttle:30,1'])
    ->name('webhooks.bagisto');

// Bayi portalı (Bagisto ozmbayi) → bayilik başvurusu köprüsü. Kayıtlar TenantApplication kuyruğuna düşer.
Route::prefix('bagisto/tenant-applications')
    ->middleware([VerifyBagistoInboundToken::class, 'throttle:30,1'])
    ->controller(TenantApplicationApiController::class)
    ->name('bagisto.tenant-applications.')
    ->group(function () {
        Route::post('/', 'store')->name('store');
        Route::get('/{token}', 'show')->where('token', '[A-Za-z0-9]{48}')->name('show');
        Route::post('/{token}/documents', 'uploadDocuments')->where('token', '[A-Za-z0-9]{48}')->name('documents');
        Route::post('/{token}/official', 'official')->where('token', '[A-Za-z0-9]{48}')->name('official');
        Route::get('/invitations/{token}', 'invitation')->where('token', '[A-Za-z0-9]{64}')->name('invitation');
        Route::post('/invitations/{token}/complete', 'complete')->where('token', '[A-Za-z0-9]{64}')->name('complete');
    });

// Bayi portalı iade sayfası → iade faturası yükleme (bayi e-postasıyla kapsamlanır).
Route::post('bagisto/returns/{rmaId}/invoice', [ReturnInvoiceApiController::class, 'store'])
    ->where('rmaId', '[0-9]+')
    ->middleware([VerifyBagistoInboundToken::class, 'throttle:20,1'])
    ->name('bagisto.returns.invoice');

// Bayi portalı "Cari Hesap" ekranı: limit/bakiye, açık faturalar, cari hareketleri (bayi e-postasıyla).
Route::get('bagisto/tenant-accounts/statement', [TenantAccountApiController::class, 'statement'])
    ->middleware([VerifyBagistoInboundToken::class, 'throttle:60,1'])
    ->name('bagisto.tenant-accounts.statement');
