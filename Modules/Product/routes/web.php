<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Http\Controllers\BrandController;
use Modules\Product\Http\Controllers\ProductAttributeController;
use Modules\Product\Http\Controllers\ProductSettingsController;
use Modules\Product\Http\Controllers\VariantOptionController;
use Modules\Product\Http\Controllers\CategoryController;
use Modules\Product\Http\Controllers\OrderController;
use Modules\Product\Http\Controllers\PriceListController;
use Modules\Product\Http\Controllers\ProductController;
use Modules\Product\Http\Controllers\ProductImageController;
use Modules\Product\Http\Controllers\StockBulkController;
use Modules\Product\Http\Controllers\StockController;
use Modules\Product\Http\Controllers\WarehouseController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/products', [ProductController::class, 'index'])
        ->middleware('can:product.view')->name('products.index');

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
    Route::put('/products/{product:id}/status', [ProductController::class, 'updateStatus'])
        ->middleware('can:product.add')
        ->whereNumber('product')
        ->name('products.update-status');
    Route::delete('/products/{product:id}', [ProductController::class, 'destroy'])
        ->middleware('can:product.delete')
        ->whereNumber('product')
        ->name('products.destroy');
    Route::post('/products/bulk-destroy', [ProductController::class, 'bulkDestroy'])
        ->middleware('can:product.delete')
        ->name('products.bulk-destroy');

    // AI destekli ürün içeriği (açıklama + SEO, Gemini). Formdaki kaydedilmemiş bilgilerle çalışır —
    // hem create hem edit formunda, bu yüzden {product} parametresi almaz. Sync — 20sn timeout kabul.
    // throttle:15,1: ücretli AI çağrısı.
    Route::post('/products/ai-content',
        [\Modules\Product\Http\Controllers\ProductAiDescriptionController::class, 'generate'])
        ->middleware(['can:product.ai.generate', 'throttle:15,1'])
        ->name('products.ai-content');

    // Barkod (EAN-13/GS1) otomatik üretimi — hem create hem edit formunda kullanılır,
    // bu yüzden {product} parametresi almaz.
    Route::post('/products/barcode/generate',
        [\Modules\Product\Http\Controllers\ProductBarcodeController::class, 'generate'])
        ->middleware('can:product.add')
        ->name('products.barcode.generate');

    Route::prefix('products/categories')->name('products.categories.')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])
            ->middleware('can:category.view')->name('index');
        Route::post('/', [CategoryController::class, 'store'])
            ->middleware('can:category.create')->name('store');
        // Ürün formundaki kategori aramasından tek isimle hızlı ekleme (dar yetki).
        Route::post('/quick-create', [CategoryController::class, 'quickStore'])
            ->middleware('can:category.create')->name('quick-store');
        Route::put('/{category}', [CategoryController::class, 'update'])
            ->middleware('can:category.edit')->name('update');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])
            ->middleware('can:category.manage')->name('destroy');
        Route::post('/bulk-destroy', [CategoryController::class, 'bulkDestroy'])
            ->middleware('can:category.manage')->name('bulk-destroy');

        Route::post('/{category}/marketplaces/{marketplace}', [CategoryController::class, 'storeMapping'])
            ->middleware('can:category.manage')->name('marketplaces.store');
        Route::delete('/{category}/marketplaces/{marketplace}', [CategoryController::class, 'destroyMapping'])
            ->middleware('can:category.manage')->name('marketplaces.destroy');

        // Kategorinin kendi global özellik seçimi (Ürün Ayarları → Özellikler'de tanımlanan)
        Route::put('/{category}/attributes', [ProductAttributeController::class, 'syncCategory'])
            ->middleware('can:category.manage')->name('attributes.sync');
    });

    Route::post('/products/marketplaces/{marketplace}/connect', [CategoryController::class, 'connectMarketplace'])
        ->middleware('can:category.manage')->name('products.marketplaces.connect');

    // ─── Ürün-Pazaryeri Listeleme ────────────────────────────────────────
    Route::get('/products/{product:id}/marketplaces/{marketplace}/listing',
        [\Modules\Product\Http\Controllers\ProductMarketplaceListingController::class, 'show'])
        ->whereNumber('product')
        ->middleware('can:product.card.view')
        ->name('products.listings.show');
    Route::put('/products/{product:id}/marketplaces/{marketplace}/listing',
        [\Modules\Product\Http\Controllers\ProductMarketplaceListingController::class, 'upsert'])
        ->whereNumber('product')
        ->middleware('can:product.add')
        ->name('products.listings.upsert');

    // ─── Markalar ────────────────────────────────────────────────────────
    Route::prefix('products/brands')->name('products.brands.')->group(function () {
        Route::get('/', [BrandController::class, 'index'])
            ->middleware('can:brand.view')->name('index');
        Route::post('/', [BrandController::class, 'store'])
            ->middleware('can:brand.manage')->name('store');
        // Ürün formundaki marka aramasından tek isimle hızlı ekleme.
        Route::post('/quick-create', [BrandController::class, 'quickStore'])
            ->middleware('can:brand.manage')->name('quick-store');
        Route::put('/{brand}', [BrandController::class, 'update'])
            ->whereNumber('brand')->middleware('can:brand.manage')->name('update');
        Route::delete('/{brand}', [BrandController::class, 'destroy'])
            ->whereNumber('brand')->middleware('can:brand.manage')->name('destroy');
    });

    // ─── Product Ayarları (tek sayfa, sekmeler; her sekme kendi yetkisiyle) ──
    Route::prefix('products/settings')->name('products.settings.')->group(function () {
        Route::get('/', [ProductSettingsController::class, 'index'])
            ->middleware('can:product-settings.view')->name('index');

        // Bedenler / Renkler sekmeleri (ürün formu varyant seçim listeleri)
        Route::post('/{type}/bulk-store', [VariantOptionController::class, 'bulkStore'])
            ->whereIn('type', ['sizes', 'colors'])->middleware('can:variant-option.create')->name('bulk-store');
        Route::post('/{type}/bulk-destroy', [VariantOptionController::class, 'bulkDestroy'])
            ->whereIn('type', ['sizes', 'colors'])->middleware('can:variant-option.delete')->name('bulk-destroy');
        Route::post('/sizes', [VariantOptionController::class, 'storeSize'])
            ->middleware('can:variant-option.create')->name('sizes.store');
        Route::put('/sizes/{size}', [VariantOptionController::class, 'updateSize'])
            ->whereNumber('size')->middleware('can:variant-option.update')->name('sizes.update');
        Route::delete('/sizes/{size}', [VariantOptionController::class, 'destroySize'])
            ->whereNumber('size')->middleware('can:variant-option.delete')->name('sizes.destroy');

        Route::post('/colors', [VariantOptionController::class, 'storeColor'])
            ->middleware('can:variant-option.create')->name('colors.store');
        Route::put('/colors/{color}', [VariantOptionController::class, 'updateColor'])
            ->whereNumber('color')->middleware('can:variant-option.update')->name('colors.update');
        Route::delete('/colors/{color}', [VariantOptionController::class, 'destroyColor'])
            ->whereNumber('color')->middleware('can:variant-option.delete')->name('colors.destroy');

        // Özellikler sekmesi (global ürün özellikleri → Bagisto attribute'ları)
        Route::post('/attributes', [ProductAttributeController::class, 'store'])
            ->middleware('can:product-attribute.create')->name('attributes.store');
        Route::post('/attributes/bulk-destroy', [ProductAttributeController::class, 'bulkDestroy'])
            ->middleware('can:product-attribute.delete')->name('attributes.bulk-destroy');
        Route::put('/attributes/{attribute}', [ProductAttributeController::class, 'update'])
            ->whereNumber('attribute')->middleware('can:product-attribute.update')->name('attributes.update');
        Route::delete('/attributes/{attribute}', [ProductAttributeController::class, 'destroy'])
            ->whereNumber('attribute')->middleware('can:product-attribute.delete')->name('attributes.destroy');
    });

    // ─── Depolar ─────────────────────────────────────────────────────────
    Route::prefix('products/warehouses')->name('products.warehouses.')->group(function () {
        Route::get('/', [WarehouseController::class, 'index'])
            ->middleware('can:warehouse.view')->name('index');
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
        // Toplu stok işlemi: ürün(ler)in bütün varyantlarına tek seferde giriş / çıkış / sayım.
        Route::get('/bulk', [StockBulkController::class, 'create'])
            ->middleware('can:stock.manage')->name('bulk');
        Route::get('/bulk/products', [StockBulkController::class, 'products'])
            ->middleware('can:stock.manage')->name('bulk.products');
        Route::post('/bulk', [StockBulkController::class, 'store'])
            ->middleware(['can:stock.manage', 'throttle:30,1'])->name('bulk.store');
    });

    // ─── Ürün Kartı (SAP MM03 benzeri) ────────────────────────────────────
    // Temel veri + stok + fiyat + pazaryeri listeleme tek sayfada. Salt-okuma
    // agregasyon — yazma aksiyonları kendi route'larında (stok/fiyat/pazaryeri)
    // kalır. Tüm fiyat listelerini + depo stoklarını gösterdiği için iç kullanıcıya
    // özel ayrı okuma yetkisi (iş ortağı rolüne verilmez).
    Route::get('/products/{product:id}/card', [\Modules\Product\Http\Controllers\ProductCardController::class, 'show'])
        ->whereNumber('product')->middleware('can:product.card.view')->name('products.card');

    // ─── Ürün Görselleri ─────────────────────────────────────────────────
    Route::prefix('products/{product:id}/images')->name('products.images.')
        ->whereNumber('product')->middleware('can:product.add')
        ->group(function () {
            Route::post('/', [ProductImageController::class, 'store'])->name('store');
            Route::put('/order', [ProductImageController::class, 'reorder'])->name('reorder');
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

    // Ürün detay slug ile çözülür. Literal `/products/*` route'larından
    // (categories, marketplaces, vb.) sonra tanımlanmalı; aksi halde
    // bu route onları yakalayıp slug eşleşmediği için 404 üretir.
    Route::get('/products/{product:slug}', [ProductController::class, 'show'])
        ->middleware('can:product.view')->name('products.show');
});
