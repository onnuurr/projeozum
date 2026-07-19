<?php

use Illuminate\Support\Facades\Route;
use Modules\Tenant\Http\Controllers\ProductAccessController;
use Modules\Tenant\Http\Controllers\TenantAccessController;
use Modules\Tenant\Http\Controllers\TenantController;
use Modules\Tenant\Http\Controllers\TenantTypeController;

Route::middleware(['auth', 'verified'])->group(function () {
    // ─── Tenant Tipleri ──────────────────────────────────────────────────
    // Literal /tenants/types yolları önce — /tenants/{tenant} catch-all'dan ÖNCE.
    Route::prefix('tenants/types')->name('tenants.types.')
        ->middleware('can:tenant-type.manage')
        ->group(function () {
            Route::get('/', [TenantTypeController::class, 'index'])->name('index');
            Route::post('/', [TenantTypeController::class, 'store'])->name('store');
            Route::put('/{type}', [TenantTypeController::class, 'update'])
                ->whereNumber('type')->name('update');
            Route::delete('/{type}', [TenantTypeController::class, 'destroy'])
                ->whereNumber('type')->name('destroy');
        });

    // ─── Tenant Erişim Yönetimi ──────────────────────────────────────────
    // (literal /tenants/{tenant}/access yolları — /tenants/{tenant} catch-all'dan önce)
    Route::prefix('tenants/{tenant}/access')->name('tenants.access.')
        ->middleware('can:tenant-access.manage')->whereNumber('tenant')
        ->group(function () {
            Route::get('/', [TenantAccessController::class, 'show'])->name('show');
            Route::post('/rules', [TenantAccessController::class, 'storeRule'])->name('rules.store');
            Route::delete('/rules/{rule}', [TenantAccessController::class, 'destroyRule'])
                ->whereNumber('rule')->name('rules.destroy');
            Route::post('/overrides', [TenantAccessController::class, 'storeOverride'])->name('overrides.store');
            Route::put('/overrides/{access}', [TenantAccessController::class, 'updateOverride'])
                ->whereNumber('access')->name('overrides.update');
            Route::put('/overrides/{access}/custom-copy', [TenantAccessController::class, 'updateCustomCopy'])
                ->whereNumber('access')->name('overrides.custom-copy');
            Route::delete('/overrides/{access}', [TenantAccessController::class, 'destroyOverride'])
                ->whereNumber('access')->name('overrides.destroy');
        });

    Route::get('/tenants/access/products/search', [TenantAccessController::class, 'searchProducts'])
        ->middleware('can:tenant-access.manage')->name('tenants.access.products.search');

    // ─── Ürün-merkezli Tenant Erişim ─────────────────────────────────────
    // (sistem yöneticisi: bir ürün × tüm tenantlar matrisini görür/düzenler)
    Route::middleware('can:tenant-access.manage')->group(function () {
        Route::get('/products/{product}/tenants', [ProductAccessController::class, 'show'])
            ->whereNumber('product')->name('products.tenants.show');
        Route::post('/products/{product}/tenants/{tenant}', [ProductAccessController::class, 'upsert'])
            ->whereNumber('product')->whereNumber('tenant')->name('products.tenants.upsert');
        Route::delete('/products/{product}/tenants/{tenant}', [ProductAccessController::class, 'destroy'])
            ->whereNumber('product')->whereNumber('tenant')->name('products.tenants.destroy');
    });

    // ─── Tenant CRUD ─────────────────────────────────────────────────────
    Route::prefix('tenants')->name('tenants.')->group(function () {
        Route::get('/', [TenantController::class, 'index'])
            ->middleware('can:tenant.view')->name('index');
        Route::post('/', [TenantController::class, 'store'])
            ->middleware('can:tenant.manage')->name('store');
        Route::put('/{tenant}', [TenantController::class, 'update'])
            ->whereNumber('tenant')->middleware('can:tenant.manage')->name('update');
        Route::post('/{tenant}/toggle', [TenantController::class, 'toggleActive'])
            ->whereNumber('tenant')->middleware('can:tenant.manage')->name('toggle');
        Route::delete('/{tenant}', [TenantController::class, 'destroy'])
            ->whereNumber('tenant')->middleware('can:tenant.manage')->name('destroy');
    });
});
