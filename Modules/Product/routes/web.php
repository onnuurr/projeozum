<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Http\Controllers\BrandController;
use Modules\Product\Http\Controllers\CartController;
use Modules\Product\Http\Controllers\CategoryController;
use Modules\Product\Http\Controllers\FavoriteController;
use Modules\Product\Http\Controllers\OrderController;
use Modules\Product\Http\Controllers\PriceListController;
use Modules\Product\Http\Controllers\ProductController;
use Modules\Product\Http\Controllers\ProductImageController;
use Modules\Product\Http\Controllers\StockController;
use Modules\Product\Http\Controllers\WarehouseController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');

    // NOT: Product::getRouteKeyName() = 'slug'. Bu yüzden numeric id ile çağrılan
    // route'lar binding'i açıkça `{product:id}` ile id'ye sabitlemeli; aksi halde
    // slug üzerinden aranır ve 404 döner.
    Route::post('/products/{product:id}/favorite', [FavoriteController::class, 'toggle'])
        ->whereNumber('product')
        ->name('products.favorite.toggle');

    // Tam sayfa ürün formları (eski drawer yerine). Slug catch-all'dan önce
    // tanımlanmalı; aksi halde `/products/create` slug olarak yorumlanır.
    Route::get('/products/create', [ProductController::class, 'create'])
        ->middleware('can:product.add')
        ->name('products.create');
    Route::get('/products/{product:id}/edit', [ProductController::class, 'edit'])
        ->whereNumber('product')
        ->middleware('can:product.add')
        ->name('products.edit');

    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('can:product.add')
        ->name('products.store');
    Route::put('/products/{product:id}', [ProductController::class, 'update'])
        ->middleware('can:product.add')
        ->whereNumber('product')
        ->name('products.update');
    Route::delete('/products/{product:id}', [ProductController::class, 'destroy'])
        ->middleware('can:product.delete')
        ->whereNumber('product')
        ->name('products.destroy');
    Route::post('/products/bulk-destroy', [ProductController::class, 'bulkDestroy'])
        ->middleware('can:product.delete')
        ->name('products.bulk-destroy');

    // AI destekli açıklama üretimi (Gemini). Sync — 20sn timeout kabul.
    Route::post('/products/{product:id}/ai-description',
        [\Modules\Product\Http\Controllers\ProductAiDescriptionController::class, 'generate'])
        ->whereNumber('product')
        ->middleware('can:product.ai.generate')
        ->name('products.ai-description');

    Route::prefix('products/categories')->name('products.categories.')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::post('/', [CategoryController::class, 'store'])
            ->middleware('can:category.manage')->name('store');
        Route::put('/{category}', [CategoryController::class, 'update'])
            ->middleware('can:category.manage')->name('update');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])
            ->middleware('can:category.manage')->name('destroy');
        Route::post('/bulk-destroy', [CategoryController::class, 'bulkDestroy'])
            ->middleware('can:category.manage')->name('bulk-destroy');

        Route::post('/{category}/marketplaces/{marketplace}', [CategoryController::class, 'storeMapping'])
            ->middleware('can:category.manage')->name('marketplaces.store');
        Route::delete('/{category}/marketplaces/{marketplace}', [CategoryController::class, 'destroyMapping'])
            ->middleware('can:category.manage')->name('marketplaces.destroy');
    });

    Route::post('/products/marketplaces/{marketplace}/connect', [CategoryController::class, 'connectMarketplace'])
        ->middleware('can:category.manage')->name('products.marketplaces.connect');

    // ─── Ürün-Pazaryeri Listeleme ────────────────────────────────────────
    Route::get('/products/{product:id}/marketplaces/{marketplace}/listing',
        [\Modules\Marketplace\Http\Controllers\ProductMarketplaceListingController::class, 'show'])
        ->whereNumber('product')
        ->name('products.listings.show');
    Route::put('/products/{product:id}/marketplaces/{marketplace}/listing',
        [\Modules\Marketplace\Http\Controllers\ProductMarketplaceListingController::class, 'upsert'])
        ->whereNumber('product')
        ->middleware('can:product.add')
        ->name('products.listings.upsert');

    // ─── Markalar ────────────────────────────────────────────────────────
    Route::prefix('products/brands')->name('products.brands.')->group(function () {
        Route::get('/', [BrandController::class, 'index'])->name('index');
        Route::post('/', [BrandController::class, 'store'])
            ->middleware('can:brand.manage')->name('store');
        Route::put('/{brand}', [BrandController::class, 'update'])
            ->whereNumber('brand')->middleware('can:brand.manage')->name('update');
        Route::delete('/{brand}', [BrandController::class, 'destroy'])
            ->whereNumber('brand')->middleware('can:brand.manage')->name('destroy');
    });

    // ─── Depolar ─────────────────────────────────────────────────────────
    Route::prefix('products/warehouses')->name('products.warehouses.')->group(function () {
        Route::get('/', [WarehouseController::class, 'index'])->name('index');
        Route::post('/', [WarehouseController::class, 'store'])
            ->middleware('can:warehouse.manage')->name('store');
        Route::put('/{warehouse:id}', [WarehouseController::class, 'update'])
            ->whereNumber('warehouse')->middleware('can:warehouse.manage')->name('update');
        Route::delete('/{warehouse:id}', [WarehouseController::class, 'destroy'])
            ->whereNumber('warehouse')->middleware('can:warehouse.manage')->name('destroy');
    });

    // ─── Stok ────────────────────────────────────────────────────────────
    Route::prefix('products/stocks')->name('products.stocks.')->group(function () {
        Route::get('/', [StockController::class, 'index'])
            ->middleware('can:stock.manage')->name('index');
        Route::post('/movement', [StockController::class, 'movement'])
            ->middleware('can:stock.manage')->name('movement');
        Route::get('/history', [StockController::class, 'history'])
            ->middleware('can:stock.manage')->name('history');
    });

    // ─── Ürün Görselleri ─────────────────────────────────────────────────
    Route::prefix('products/{product:id}/images')->name('products.images.')
        ->whereNumber('product')->middleware('can:product.add')
        ->group(function () {
            Route::post('/', [ProductImageController::class, 'store'])->name('store');
        });
    Route::put('/products/images/{image}', [ProductImageController::class, 'update'])
        ->whereNumber('image')->middleware('can:product.add')->name('products.images.update');
    Route::delete('/products/images/{image}', [ProductImageController::class, 'destroy'])
        ->whereNumber('image')->middleware('can:product.add')->name('products.images.destroy');

    // ─── Fiyat Listeleri ─────────────────────────────────────────────────
    Route::post('/products/variants/{variant}/prices', [PriceListController::class, 'store'])
        ->whereNumber('variant')->middleware('can:price-list.manage')
        ->name('products.prices.store');
    Route::put('/products/prices/{priceList}', [PriceListController::class, 'update'])
        ->whereNumber('priceList')->middleware('can:price-list.manage')
        ->name('products.prices.update');
    Route::delete('/products/prices/{priceList}', [PriceListController::class, 'destroy'])
        ->whereNumber('priceList')->middleware('can:price-list.manage')
        ->name('products.prices.destroy');

    // ─── Kargo Firmaları (superadmin yönetimli referans) ─────────────────
    Route::prefix('products/carriers')->name('carriers.')
        ->middleware('can:carrier.manage')
        ->group(function () {
            Route::get('/', [\Modules\Product\Http\Controllers\CarrierController::class, 'index'])->name('index');
            Route::post('/', [\Modules\Product\Http\Controllers\CarrierController::class, 'store'])->name('store');
            Route::put('/{carrier}', [\Modules\Product\Http\Controllers\CarrierController::class, 'update'])
                ->whereNumber('carrier')->name('update');
            Route::delete('/{carrier}', [\Modules\Product\Http\Controllers\CarrierController::class, 'destroy'])
                ->whereNumber('carrier')->name('destroy');
        });

    // ─── Admin Sipariş Yönetimi (Faz 2) ──────────────────────────────────
    // Ana domain. Portal /orders subdomain'de ayrı (PortalOrderController,
    // salt-okuma) tanımlıdır; subdomain routing çakışma yaratmaz.
    Route::get('/orders', [OrderController::class, 'index'])
        ->middleware('can:order.view')->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])
        ->whereNumber('order')->middleware('can:order.view')->name('orders.show');
    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus'])
        ->whereNumber('order')->middleware('can:order.manage')->name('orders.update-status');

    Route::prefix('cart')->name('cart.')->group(function () {
        Route::post('/', [CartController::class, 'add'])->name('add');
        Route::put('/{cartItem}', [CartController::class, 'updateQty'])
            ->whereNumber('cartItem')
            ->name('update');
        Route::delete('/{cartItem}', [CartController::class, 'remove'])
            ->whereNumber('cartItem')
            ->name('remove');
        Route::delete('/', [CartController::class, 'clear'])->name('clear');
    });

    // NOT (Faz 3 · D5): ana domain B2C `/checkout` ve `/checkout/addresses/*` rotaları
    // kaldırıldı (tenant-only B2B; checkout portal subdomain'de PortalCheckoutController).
    // `/cart/*` KALIR — portal bu sepet altyapısını subdomain üzerinden kullanır.

    // Ürün detay slug ile çözülür. Literal `/products/*` route'larından
    // (categories, marketplaces, vb.) sonra tanımlanmalı; aksi halde
    // bu route onları yakalayıp slug eşleşmediği için 404 üretir.
    Route::get('/products/{product:slug}', [ProductController::class, 'show'])
        ->name('products.show');
});
