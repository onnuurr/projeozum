<?php

use Illuminate\Support\Facades\Route;
use Modules\Marketplace\Http\Controllers\Api\Webhooks\CiceksepetiWebhookController;
use Modules\Marketplace\Http\Controllers\Api\Webhooks\HepsiburadaWebhookController;
use Modules\Marketplace\Http\Controllers\Api\Webhooks\N11WebhookController;
use Modules\Marketplace\Http\Controllers\Api\Webhooks\TrendyolWebhookController;

// Marketplace webhook endpoint'leri — auth-siz; her controller kendi HMAC + tenant resolve eder.
Route::post('webhooks/trendyol',    [TrendyolWebhookController::class,    'handle'])->name('webhooks.trendyol');
Route::post('webhooks/hepsiburada', [HepsiburadaWebhookController::class, 'handle'])->name('webhooks.hepsiburada');
Route::post('webhooks/n11',         [N11WebhookController::class,         'handle'])->name('webhooks.n11');
Route::post('webhooks/ciceksepeti', [CiceksepetiWebhookController::class, 'handle'])->name('webhooks.ciceksepeti');
