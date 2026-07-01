<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\DashboardController;
use Modules\Finance\Http\Controllers\ProductCostReportController;
use Modules\Finance\Http\Controllers\SalesHistoryReportController;
use Modules\Finance\Http\Controllers\TenantPurchaseReportController;

// Finans modülü tamamen iç kullanım içindir (tenant'lara kapalı); tüm route'lar
// tek bir 'finance.manage' iznine bağlıdır (bkz. laravel-authorization skill kararı).
Route::middleware(['auth', 'verified', 'can:finance.manage'])->prefix('finance')->name('finance.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/product-costs', [ProductCostReportController::class, 'index'])->name('product-costs');
    Route::get('/sales', [SalesHistoryReportController::class, 'index'])->name('sales');
    Route::get('/tenant-purchases', [TenantPurchaseReportController::class, 'index'])->name('tenant-purchases');
});
