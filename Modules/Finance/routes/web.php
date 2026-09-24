<?php

use Illuminate\Support\Facades\Route;
use Modules\Finance\Http\Controllers\BankAccountController;
use Modules\Finance\Http\Controllers\BankStatementController;
use Modules\Finance\Http\Controllers\DashboardController;
use Modules\Finance\Http\Controllers\OutgoingInvoiceController;
use Modules\Finance\Http\Controllers\ProductCostReportController;
use Modules\Finance\Http\Controllers\ProformaInvoiceController;
use Modules\Finance\Http\Controllers\SalesHistoryReportController;
use Modules\Finance\Http\Controllers\SupplierInvoiceController;
use Modules\Finance\Http\Controllers\TenantPurchaseReportController;

// Finans modülü tamamen iç kullanım içindir (tenant'lara kapalı). Yetkiler (2026-09-24):
//   finance.view          → tüm GET'ler (raporlar, listeler, fatura dosyası indirme)
//   finance.manage        → kayıt ekle/düzenle/sil, ödendi işaretle, dönüştür, ekstre, mutabakat
//   finance.einvoice.send → e-Faturayı GİB'e gönder (geri alınamaz)
Route::middleware(['auth', 'verified', 'can:finance.view'])->prefix('finance')->name('finance.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/product-costs', [ProductCostReportController::class, 'index'])->name('product-costs');
    Route::get('/sales', [SalesHistoryReportController::class, 'index'])->name('sales');
    Route::get('/tenant-purchases', [TenantPurchaseReportController::class, 'index'])->name('tenant-purchases');

    // Alınan faturalar (tedarikçi/gider faturaları)
    Route::prefix('supplier-invoices')->name('supplier-invoices.')->group(function () {
        Route::get('/', [SupplierInvoiceController::class, 'index'])->name('index');
        Route::post('/', [SupplierInvoiceController::class, 'store'])->middleware('can:finance.manage')->name('store');
        Route::put('/{supplierInvoice}', [SupplierInvoiceController::class, 'update'])->middleware('can:finance.manage')->name('update');
        Route::delete('/{supplierInvoice}', [SupplierInvoiceController::class, 'destroy'])->middleware('can:finance.manage')->name('destroy');
        Route::post('/{supplierInvoice}/mark-paid', [SupplierInvoiceController::class, 'markPaid'])->middleware('can:finance.manage')->name('mark-paid');
        Route::get('/{supplierInvoice}/file', [SupplierInvoiceController::class, 'downloadFile'])->name('download-file');
    });

    // Proforma faturalar
    Route::prefix('proformas')->name('proformas.')->group(function () {
        Route::get('/', [ProformaInvoiceController::class, 'index'])->name('index');
        Route::post('/', [ProformaInvoiceController::class, 'store'])->middleware('can:finance.manage')->name('store');
        Route::put('/{proformaInvoice}', [ProformaInvoiceController::class, 'update'])->middleware('can:finance.manage')->name('update');
        Route::delete('/{proformaInvoice}', [ProformaInvoiceController::class, 'destroy'])->middleware('can:finance.manage')->name('destroy');
        Route::post('/{proformaInvoice}/convert', [ProformaInvoiceController::class, 'convert'])->middleware('can:finance.manage')->name('convert');
    });

    // Düzenlenen faturalar (e-Fatura soyutlaması ile)
    Route::prefix('outgoing-invoices')->name('outgoing-invoices.')->group(function () {
        Route::get('/', [OutgoingInvoiceController::class, 'index'])->name('index');
        Route::post('/', [OutgoingInvoiceController::class, 'store'])->middleware('can:finance.manage')->name('store');
        Route::put('/{outgoingInvoice}', [OutgoingInvoiceController::class, 'update'])->middleware('can:finance.manage')->name('update');
        Route::delete('/{outgoingInvoice}', [OutgoingInvoiceController::class, 'destroy'])->middleware('can:finance.manage')->name('destroy');
        Route::post('/{outgoingInvoice}/send', [OutgoingInvoiceController::class, 'send'])->middleware('can:finance.einvoice.send')->name('send');
    });

    // Banka hesapları
    Route::prefix('bank-accounts')->name('bank-accounts.')->group(function () {
        Route::get('/', [BankAccountController::class, 'index'])->name('index');
        Route::post('/', [BankAccountController::class, 'store'])->middleware('can:finance.manage')->name('store');
        Route::put('/{bankAccount}', [BankAccountController::class, 'update'])->middleware('can:finance.manage')->name('update');
        Route::delete('/{bankAccount}', [BankAccountController::class, 'destroy'])->middleware('can:finance.manage')->name('destroy');
    });

    // Banka ekstresi içe aktarma + mutabakat
    Route::prefix('bank-statements')->name('bank-statements.')->group(function () {
        Route::get('/', [BankStatementController::class, 'index'])->name('index');
        Route::post('/import', [BankStatementController::class, 'import'])->middleware('can:finance.manage')->name('import');
        Route::post('/transactions/{bankTransaction}/match', [BankStatementController::class, 'match'])->middleware('can:finance.manage')->name('transactions.match');
        Route::post('/transactions/{bankTransaction}/ignore', [BankStatementController::class, 'ignore'])->middleware('can:finance.manage')->name('transactions.ignore');
    });
});
