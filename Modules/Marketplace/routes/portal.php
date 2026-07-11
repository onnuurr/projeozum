<?php

use Illuminate\Support\Facades\Route;
use Modules\Marketplace\Http\Controllers\Portal\Marketplace\CiceksepetiController;
use Modules\Marketplace\Http\Controllers\Portal\Marketplace\HepsiburadaController;
use Modules\Marketplace\Http\Controllers\Portal\Marketplace\N11Controller;
use Modules\Marketplace\Http\Controllers\Portal\Marketplace\TrendyolController;
use Modules\Marketplace\Http\Controllers\Portal\MarketplaceHubController;

/*
|--------------------------------------------------------------------------
| Marketplace Portal Routes
|--------------------------------------------------------------------------
|
| root routes/web.php'deki portal subdomain group'u tarafından yüklenir
| (Tenant portal.php ile aynı subdomain + 'portal.' name prefix + middleware:
| web,auth,verified,active,tenant.subdomain,can:portal.access). Buraya tekrar yazma.
*/

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
