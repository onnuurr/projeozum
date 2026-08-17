<?php

use Illuminate\Support\Facades\Route;
use Modules\Bagisto\Http\Controllers\Api\OrderWebhookController;
use Modules\Bagisto\Http\Middleware\VerifyBagistoInboundToken;

// Bagisto'nun `PushEventToSaas` job'ının hedefi (order.created / order.cancelled).
// Auth: Bearer token (VerifyBagistoInboundToken) — HMAC değil.
Route::post('webhooks/bagisto', [OrderWebhookController::class, 'handle'])
    ->middleware(VerifyBagistoInboundToken::class)
    ->name('webhooks.bagisto');
