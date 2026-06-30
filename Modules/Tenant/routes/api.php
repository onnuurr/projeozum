<?php

use Illuminate\Support\Facades\Route;
use Modules\Tenant\Http\Controllers\Api\TenantApiController;
use Modules\Tenant\Http\Controllers\Api\TenantInvoiceApiController;
use Modules\Tenant\Http\Controllers\Api\TenantPriceListApiController;
use Modules\Tenant\Http\Controllers\Api\TenantSettingsApiController;
use Modules\Tenant\Http\Controllers\Api\Webhooks\CiceksepetiWebhookController;
use Modules\Tenant\Http\Controllers\Api\Webhooks\HepsiburadaWebhookController;
use Modules\Tenant\Http\Controllers\Api\Webhooks\N11WebhookController;
use Modules\Tenant\Http\Controllers\Api\Webhooks\TrendyolWebhookController;

// Marketplace webhook endpoint'leri — auth-siz; her controller kendi HMAC + tenant resolve eder.
Route::post('webhooks/trendyol',    [TrendyolWebhookController::class,    'handle'])->name('webhooks.trendyol');
Route::post('webhooks/hepsiburada', [HepsiburadaWebhookController::class, 'handle'])->name('webhooks.hepsiburada');
Route::post('webhooks/n11',         [N11WebhookController::class,         'handle'])->name('webhooks.n11');
Route::post('webhooks/ciceksepeti', [CiceksepetiWebhookController::class, 'handle'])->name('webhooks.ciceksepeti');

// Tüm v1 tenant yönetim uçları can:tenant.manage ister (web tarafıyla tutarlı; superadmin
// Gate::before ile geçer). Böylece tenant yönetimi superadmin dışı rollere delege edilebilir.
Route::middleware(['auth:sanctum', 'can:tenant.manage'])->prefix('v1')->group(function () {
    // Tenant koleksiyon endpoint'leri — kullanıcı sadece kendi tenant'ını görür (superadmin hepsini).
    Route::get('tenants', [TenantApiController::class, 'index']);
    Route::post('tenants', [TenantApiController::class, 'store']);

    // {tenant} param'lı endpoint'lere ayrıca tenant.owns eklenir → tenant kullanıcısı sadece
    // kendi tenant'ına ulaşır; superadmin bypass.
    Route::middleware('tenant.owns')->group(function () {
        Route::get('tenants/{tenant}', [TenantApiController::class, 'show']);
        Route::put('tenants/{tenant}', [TenantApiController::class, 'update']);
        Route::delete('tenants/{tenant}', [TenantApiController::class, 'destroy']);
        Route::patch('tenants/{tenant}/suspend', [TenantApiController::class, 'suspend']);
        Route::patch('tenants/{tenant}/activate', [TenantApiController::class, 'activate']);

        // Price Lists
        Route::get('tenants/{tenant}/price-lists', [TenantPriceListApiController::class, 'index']);
        Route::post('tenants/{tenant}/price-lists', [TenantPriceListApiController::class, 'store']);
        Route::delete('tenants/{tenant}/price-lists/{priceList}', [TenantPriceListApiController::class, 'destroy']);

        // Invoices
        Route::get('tenants/{tenant}/invoices', [TenantInvoiceApiController::class, 'index']);
        Route::post('tenants/{tenant}/invoices', [TenantInvoiceApiController::class, 'store']);
        Route::patch('tenants/{tenant}/invoices/{invoice}/mark-paid', [TenantInvoiceApiController::class, 'markPaid']);

        // Settings
        Route::get('tenants/{tenant}/settings', [TenantSettingsApiController::class, 'show']);
        Route::patch('tenants/{tenant}/settings', [TenantSettingsApiController::class, 'update']);
    });
});
