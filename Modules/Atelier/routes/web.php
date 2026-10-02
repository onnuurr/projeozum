<?php

use Illuminate\Support\Facades\Route;

// Yetkiler hibrit granülerdir (bkz. AtelierPermissionSeeder): okuma için atelier.view,
// yazma işlemleri için alt-özellik bazlı can:* izinleri. Sert role:superadmin kilidi YOK —
// izinler delege edilebilir; superadmin Gate::before ile hepsini geçer.
Route::middleware(['auth', 'verified'])
    ->prefix('atelier')
    ->name('atelier.')
    ->group(function () {
        Route::get('/', [\Modules\Atelier\Http\Controllers\DashboardController::class, 'index'])
            ->middleware('can:atelier.view')->name('dashboard');

        // Hammaddeler
        Route::get('materials', [\Modules\Atelier\Http\Controllers\MaterialController::class, 'index'])
            ->middleware('can:atelier.view')->name('materials.index');
        Route::get('materials-lookup', [\Modules\Atelier\Http\Controllers\MaterialLookupController::class, 'search'])
            ->middleware('can:atelier.view')->name('materials.lookup');
        Route::post('materials', [\Modules\Atelier\Http\Controllers\MaterialController::class, 'store'])
            ->middleware('can:atelier.material.manage')->name('materials.store');
        Route::put('materials/{material}', [\Modules\Atelier\Http\Controllers\MaterialController::class, 'update'])
            ->middleware('can:atelier.material.manage')->name('materials.update');
        Route::put('materials/{material}/specs', [\Modules\Atelier\Http\Controllers\MaterialController::class, 'updateSpecs'])
            ->middleware('can:atelier.material.manage')->name('materials.specs.update');
        Route::delete('materials/{material}', [\Modules\Atelier\Http\Controllers\MaterialController::class, 'destroy'])
            ->middleware('can:atelier.material.manage')->name('materials.destroy');
        Route::post('materials/movement', [\Modules\Atelier\Http\Controllers\MaterialController::class, 'movement'])
            ->middleware('can:atelier.material.manage')->name('materials.movement');

        // Reçeteler (BOM)
        Route::get('boms', [\Modules\Atelier\Http\Controllers\BomController::class, 'index'])
            ->middleware('can:atelier.view')->name('boms.index');
        Route::post('boms', [\Modules\Atelier\Http\Controllers\BomController::class, 'store'])
            ->middleware('can:atelier.bom.manage')->name('boms.store');
        Route::delete('boms/{bom}', [\Modules\Atelier\Http\Controllers\BomController::class, 'destroy'])
            ->middleware('can:atelier.bom.manage')->name('boms.destroy');
        // Ürün rotası (reçetenin yanındaki ana veri; yeni iş emri bundan dolar)
        Route::put('products/{product:id}/route', [\Modules\Atelier\Http\Controllers\BomController::class, 'updateRoute'])
            ->middleware('can:atelier.bom.manage')->name('products.route.update');

        // Üretilecek ürün: reçete + ön maliyetle geçici stok kartı (ilk iş emri tamamlanınca ürüne döner)
        Route::get('new-product', [\Modules\Atelier\Http\Controllers\DevelopmentProductController::class, 'create'])
            ->middleware(['can:product.add', 'can:atelier.bom.manage'])->name('development-products.create');
        Route::post('new-product', [\Modules\Atelier\Http\Controllers\DevelopmentProductController::class, 'store'])
            ->middleware(['can:product.add', 'can:atelier.bom.manage'])->name('development-products.store');

        // Operasyonlar
        Route::get('operations', [\Modules\Atelier\Http\Controllers\OperationController::class, 'index'])
            ->middleware('can:atelier.view')->name('operations.index');
        Route::post('operations', [\Modules\Atelier\Http\Controllers\OperationController::class, 'store'])
            ->middleware('can:atelier.operation.manage')->name('operations.store');
        Route::put('operations/{operation}', [\Modules\Atelier\Http\Controllers\OperationController::class, 'update'])
            ->middleware('can:atelier.operation.manage')->name('operations.update');
        Route::delete('operations/{operation}', [\Modules\Atelier\Http\Controllers\OperationController::class, 'destroy'])
            ->middleware('can:atelier.operation.manage')->name('operations.destroy');

        // Fasoncular
        Route::get('fason-suppliers', [\Modules\Atelier\Http\Controllers\FasonSupplierController::class, 'index'])
            ->middleware('can:atelier.view')->name('fason-suppliers.index');
        Route::post('fason-suppliers', [\Modules\Atelier\Http\Controllers\FasonSupplierController::class, 'store'])
            ->middleware('can:atelier.fason.manage')->name('fason-suppliers.store');
        Route::put('fason-suppliers/{fasonSupplier}', [\Modules\Atelier\Http\Controllers\FasonSupplierController::class, 'update'])
            ->middleware('can:atelier.fason.manage')->name('fason-suppliers.update');
        Route::delete('fason-suppliers/{fasonSupplier}', [\Modules\Atelier\Http\Controllers\FasonSupplierController::class, 'destroy'])
            ->middleware('can:atelier.fason.manage')->name('fason-suppliers.destroy');

        // İş emirleri
        Route::get('production-orders', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'index'])
            ->middleware('can:atelier.view')->name('production-orders.index');
        Route::get('production-orders/plan-preview', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'planPreview'])
            ->middleware('can:atelier.view')->name('production-orders.plan-preview');
        Route::get('production-orders/{productionOrder}', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'show'])
            ->middleware('can:atelier.view')->name('production-orders.show');
        Route::post('production-orders', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'store'])
            ->middleware('can:atelier.production.manage')->name('production-orders.store');
        Route::post('production-orders/{productionOrder}/plan', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'plan'])
            ->middleware('can:atelier.production.manage')->name('production-orders.plan');
        Route::post('production-orders/{productionOrder}/steps/{step}/batches', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'recordBatch'])
            ->middleware('can:atelier.production.manage')->name('production-orders.steps.batches.store');
        Route::put('production-orders/{productionOrder}/steps/{step}/decision', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'updateDecision'])
            ->middleware('can:atelier.production.manage')->name('production-orders.steps.decision.update');
        Route::post('production-orders/{productionOrder}/receive', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'receive'])
            ->middleware('can:atelier.production.manage')->name('production-orders.receive');
        Route::post('production-orders/{productionOrder}/close', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'close'])
            ->middleware('can:atelier.production.manage')->name('production-orders.close');
        Route::post('production-orders/{productionOrder}/cancel', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'cancel'])
            ->middleware('can:atelier.production.manage')->name('production-orders.cancel');

        // Kalıp Kütüphanesi / Konsept Stüdyosu / Dönüştürücü / Atama route'ları
        // 2026-09-22'de Modules/PatternEngine/routes/web.php'ye taşındı (aynı
        // 'atelier.' prefix/name grubu altında — URL ve route adları değişmedi,
        // bkz. docs/superpowers/specs/2026-09-22-modul-calisma-mantigi-revizyonu-design.md §3).
    });
