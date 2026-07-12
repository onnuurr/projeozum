<?php

use Illuminate\Support\Facades\Route;
use Modules\Marketplace\Http\Controllers\CategoryMappingController;
use Modules\Marketplace\Http\Controllers\TenantMarketplaceController;

Route::middleware(['auth', 'verified'])->group(function () {
    // ─── Pazaryeri Bağlantıları ──────────────────────────────────────────
    // (literal /tenants/{tenant}/marketplace yolları — /tenants/{tenant} catch-all'dan önce)
    // Permission tek başına yetmez: controller'da scope check (superadmin tümü, tenant kullanıcı kendisi).
    Route::prefix('tenants/{tenant}/marketplace')->name('tenants.marketplace.')
        ->middleware('can:marketplace.manage')->whereNumber('tenant')
        ->group(function () {
            Route::get('/', [TenantMarketplaceController::class, 'index'])->name('index');
            Route::post('/', [TenantMarketplaceController::class, 'store'])->name('store');
            Route::put('/{credential}', [TenantMarketplaceController::class, 'update'])
                ->whereNumber('credential')->name('update');
            Route::post('/{credential}/toggle', [TenantMarketplaceController::class, 'toggle'])
                ->whereNumber('credential')->name('toggle');
            Route::delete('/{credential}', [TenantMarketplaceController::class, 'destroy'])
                ->whereNumber('credential')->name('destroy');
        });

    // ─── İç Katalog: Kategori ↔ Pazaryeri Eşleme ─────────────────────────
    // Product'ın Kategoriler sayfasından taşındı — iç/admin-only, marketplace.catalog.manage.
    Route::prefix('marketplace/categories')->name('marketplace.categories.')->group(function () {
        Route::get('/', [CategoryMappingController::class, 'index'])->name('index');
        Route::post('/marketplaces/{marketplace}/connect', [CategoryMappingController::class, 'connect'])
            ->middleware('can:marketplace.catalog.manage')->name('connect');
        Route::post('/{category}/marketplaces/{marketplace}', [CategoryMappingController::class, 'storeMapping'])
            ->middleware('can:marketplace.catalog.manage')->name('mappings.store');
        Route::delete('/{category}/marketplaces/{marketplace}', [CategoryMappingController::class, 'destroyMapping'])
            ->middleware('can:marketplace.catalog.manage')->name('mappings.destroy');
    });
});
