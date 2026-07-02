<?php

use Illuminate\Support\Facades\Route;
use Modules\Tenant\Http\Controllers\Portal\Marketplace\CiceksepetiController;
use Modules\Tenant\Http\Controllers\Portal\Marketplace\HepsiburadaController;
use Modules\Tenant\Http\Controllers\Portal\Marketplace\N11Controller;
use Modules\Tenant\Http\Controllers\Portal\Marketplace\TrendyolController;
use Modules\Tenant\Http\Controllers\Portal\MarketplaceHubController;
use Modules\Tenant\Http\Controllers\Portal\PortalCatalogController;
use Modules\Tenant\Http\Controllers\Portal\PortalCheckoutController;
use Modules\Tenant\Http\Controllers\Portal\PortalCreditController;
use Modules\Tenant\Http\Controllers\Portal\PortalDashboardController;
use Modules\Tenant\Http\Controllers\Portal\PortalFinancialsController;
use Modules\Tenant\Http\Controllers\Portal\PortalInvoiceController;
use Modules\Tenant\Http\Controllers\Portal\PortalOrderController;
use Modules\Tenant\Http\Controllers\Portal\PortalProfitController;
use Modules\Tenant\Http\Controllers\Portal\PortalUserController;

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

Route::middleware('can:portal.catalog.view')->group(function () {
    Route::get('/catalog',                  [PortalCatalogController::class, 'index'])->name('catalog.index');
    Route::get('/catalog/{product:slug}',   [PortalCatalogController::class, 'show'])->name('catalog.show');
});

Route::middleware('can:portal.checkout')->group(function () {
    Route::get('/checkout',                 [PortalCheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout',                [PortalCheckoutController::class, 'store'])->name('checkout.store');
});

// Pazaryeri hub + provider başına ayrı route group (her birinin kendi controller'ı).
Route::middleware('can:marketplace.view-sales')->group(function () {
    Route::get('/marketplace', [MarketplaceHubController::class, 'index'])->name('marketplace.index');

    Route::prefix('marketplace/trendyol')->name('marketplace.trendyol.')->group(function () {
        Route::get('/',                  [TrendyolController::class, 'index'])->name('index');
        Route::post('/push',             [TrendyolController::class, 'pushProducts'])->middleware('can:marketplace.sync')->name('push');
        Route::post('/pull-orders',      [TrendyolController::class, 'pullOrders'])->middleware('can:marketplace.sync')->name('pull-orders');
        Route::get('/listings',          [TrendyolController::class, 'listings'])->name('listings');
        Route::get('/category-tree',     [TrendyolController::class, 'categoryTree'])->name('category-tree');
    });

    Route::get('/marketplace/hepsiburada', [HepsiburadaController::class, 'index'])->name('marketplace.hepsiburada.index');
    Route::get('/marketplace/n11',         [N11Controller::class, 'index'])->name('marketplace.n11.index');
    Route::get('/marketplace/ciceksepeti', [CiceksepetiController::class, 'index'])->name('marketplace.ciceksepeti.index');
});

Route::middleware('can:portal.calculator.use')->group(function () {
    Route::get('/profit',          [PortalProfitController::class, 'index'])->name('profit.index');
    Route::post('/profit',         [PortalProfitController::class, 'compute'])->name('profit.compute');
    Route::get('/profit/search',   [PortalProfitController::class, 'searchProducts'])->name('profit.search');
});

Route::middleware('can:portal.financials.view')->group(function () {
    Route::get('/financials', [PortalFinancialsController::class, 'index'])->name('financials');
});

Route::middleware('can:portal.users.manage')->group(function () {
    Route::get('/users',                          [PortalUserController::class, 'index'])->name('users.index');
    Route::post('/users',                         [PortalUserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}',                   [PortalUserController::class, 'update'])->whereNumber('user')->name('users.update');
    Route::delete('/users/{user}',                [PortalUserController::class, 'destroy'])->whereNumber('user')->name('users.destroy');
    Route::post('/users/{user}/toggle-active',    [PortalUserController::class, 'toggleActive'])->whereNumber('user')->name('users.toggle-active');
    Route::post('/users/{user}/reset-password',   [PortalUserController::class, 'resetPassword'])->whereNumber('user')->name('users.reset-password');
});
