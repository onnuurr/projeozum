<?php

use Illuminate\Support\Facades\Route;
use Modules\Atelier\Http\Controllers\AtelierController;

Route::middleware(['auth', 'verified', 'role:superadmin', 'can:atelier.manage'])
    ->prefix('atelier')
    ->name('atelier.')
    ->group(function () {
        Route::get('/', [AtelierController::class, 'index'])->name('dashboard');

        // Hammaddeler
        Route::get('materials', [\Modules\Atelier\Http\Controllers\MaterialController::class, 'index'])->name('materials.index');
        Route::post('materials', [\Modules\Atelier\Http\Controllers\MaterialController::class, 'store'])->name('materials.store');
        Route::put('materials/{material}', [\Modules\Atelier\Http\Controllers\MaterialController::class, 'update'])->name('materials.update');
        Route::delete('materials/{material}', [\Modules\Atelier\Http\Controllers\MaterialController::class, 'destroy'])->name('materials.destroy');
        Route::post('materials/movement', [\Modules\Atelier\Http\Controllers\MaterialController::class, 'movement'])->name('materials.movement');

        // Reçeteler (BOM)
        Route::get('boms', [\Modules\Atelier\Http\Controllers\BomController::class, 'index'])->name('boms.index');
        Route::post('boms', [\Modules\Atelier\Http\Controllers\BomController::class, 'store'])->name('boms.store');
        Route::delete('boms/{bom}', [\Modules\Atelier\Http\Controllers\BomController::class, 'destroy'])->name('boms.destroy');

        // Operasyonlar
        Route::get('operations', [\Modules\Atelier\Http\Controllers\OperationController::class, 'index'])->name('operations.index');
        Route::post('operations', [\Modules\Atelier\Http\Controllers\OperationController::class, 'store'])->name('operations.store');
        Route::put('operations/{operation}', [\Modules\Atelier\Http\Controllers\OperationController::class, 'update'])->name('operations.update');
        Route::delete('operations/{operation}', [\Modules\Atelier\Http\Controllers\OperationController::class, 'destroy'])->name('operations.destroy');

        // Fasoncular
        Route::get('fason-suppliers', [\Modules\Atelier\Http\Controllers\FasonSupplierController::class, 'index'])->name('fason-suppliers.index');
        Route::post('fason-suppliers', [\Modules\Atelier\Http\Controllers\FasonSupplierController::class, 'store'])->name('fason-suppliers.store');
        Route::put('fason-suppliers/{fasonSupplier}', [\Modules\Atelier\Http\Controllers\FasonSupplierController::class, 'update'])->name('fason-suppliers.update');
        Route::delete('fason-suppliers/{fasonSupplier}', [\Modules\Atelier\Http\Controllers\FasonSupplierController::class, 'destroy'])->name('fason-suppliers.destroy');

        // İş emirleri
        Route::get('production-orders', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'index'])->name('production-orders.index');
        Route::get('production-orders/plan-preview', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'planPreview'])->name('production-orders.plan-preview');
        Route::get('production-orders/{productionOrder}', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'show'])->name('production-orders.show');
        Route::post('production-orders', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'store'])->name('production-orders.store');
        Route::post('production-orders/{productionOrder}/plan', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'plan'])->name('production-orders.plan');
        Route::post('production-orders/{productionOrder}/complete', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'complete'])->name('production-orders.complete');
        Route::post('production-orders/{productionOrder}/cancel', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'cancel'])->name('production-orders.cancel');
        Route::put('production-orders/{productionOrder}/items', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'updateItem'])->name('production-orders.items.update');
        Route::put('production-orders/{productionOrder}/steps/{step}', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'updateStep'])->name('production-orders.steps.update');
    });
