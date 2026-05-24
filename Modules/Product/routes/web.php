<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Http\Controllers\AddressController;
use Modules\Product\Http\Controllers\BrandController;
use Modules\Product\Http\Controllers\CartController;
use Modules\Product\Http\Controllers\CategoryController;
use Modules\Product\Http\Controllers\CheckoutController;
use Modules\Product\Http\Controllers\FavoriteController;
use Modules\Product\Http\Controllers\PriceListController;
use Modules\Product\Http\Controllers\ProductController;
use Modules\Product\Http\Controllers\ProductImageController;
use Modules\Product\Http\Controllers\StockController;
use Modules\Product\Http\Controllers\WarehouseController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');

    Route::post('/products/{product}/favorite', [FavoriteController::class, 'toggle'])
        ->whereNumber('product')
        ->name('products.favorite.toggle');

    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('can:product.add')
        ->name('products.store');
    Route::put('/products/{product}', [ProductController::class, 'update'])
        ->middleware('can:product.add')
        ->whereNumber('product')
        ->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->middleware('can:product.delete')
        ->whereNumber('product')
        ->name('products.destroy');

    Route::prefix('products/categories')->name('products.categories.')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');

        Route::post('/{category}/marketplaces/{marketplace}', [CategoryController::class, 'storeMapping'])
            ->name('marketplaces.store');
        Route::delete('/{category}/marketplaces/{marketplace}', [CategoryController::class, 'destroyMapping'])
            ->name('marketplaces.destroy');
    });

    Route::post('/products/marketplaces/{marketplace}/connect', [CategoryController::class, 'connectMarketplace'])
        ->name('products.marketplaces.connect');

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
    Route::prefix('products/{product}/images')->name('products.images.')
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

    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    Route::prefix('checkout/addresses')->name('checkout.addresses.')->group(function () {
        Route::post('/', [AddressController::class, 'store'])->name('store');
        Route::put('/{address}', [AddressController::class, 'update'])
            ->whereNumber('address')
            ->name('update');
        Route::delete('/{address}', [AddressController::class, 'destroy'])
            ->whereNumber('address')
            ->name('destroy');
    });

    // Ürün detay slug ile çözülür. Literal `/products/*` route'larından
    // (categories, marketplaces, vb.) sonra tanımlanmalı; aksi halde
    // bu route onları yakalayıp slug eşleşmediği için 404 üretir.
    Route::get('/products/{product:slug}', [ProductController::class, 'show'])
        ->name('products.show');
});
