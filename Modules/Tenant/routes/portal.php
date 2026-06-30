<?php

use Illuminate\Support\Facades\Route;
use Modules\Tenant\Http\Controllers\Portal\PortalCreditController;
use Modules\Tenant\Http\Controllers\Portal\PortalDashboardController;
use Modules\Tenant\Http\Controllers\Portal\PortalInvoiceController;
use Modules\Tenant\Http\Controllers\Portal\PortalOrderController;

/*
|--------------------------------------------------------------------------
| Tenant Portal Routes
|--------------------------------------------------------------------------
|
| RouteServiceProvider::mapPortalRoutes() tarafından subdomain group altında
| yüklenir. Middleware'ler (web,auth,verified,tenant.subdomain,can:portal.access)
| zaten dış group'tan gelir; buraya tekrar yazma.
*/

Route::get('/', PortalDashboardController::class)->name('dashboard');

Route::middleware('can:portal.orders.view')->group(function () {
    Route::get('/orders',                  [PortalOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}',          [PortalOrderController::class, 'show'])->whereNumber('order')->name('orders.show');
});

Route::middleware('can:portal.invoices.view')->group(function () {
    Route::get('/invoices',                        [PortalInvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}',              [PortalInvoiceController::class, 'show'])->whereNumber('invoice')->name('invoices.show');
    Route::get('/invoices/{invoice}/payload',      [PortalInvoiceController::class, 'payload'])->whereNumber('invoice')->name('invoices.payload');
});

Route::middleware('can:portal.credit.view')->group(function () {
    Route::get('/credit',                  [PortalCreditController::class, 'index'])->name('credit');
});
