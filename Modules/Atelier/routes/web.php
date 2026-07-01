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
        Route::post('production-orders/{productionOrder}/complete', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'complete'])
            ->middleware('can:atelier.production.manage')->name('production-orders.complete');
        Route::post('production-orders/{productionOrder}/cancel', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'cancel'])
            ->middleware('can:atelier.production.manage')->name('production-orders.cancel');
        Route::put('production-orders/{productionOrder}/items', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'updateItem'])
            ->middleware('can:atelier.production.manage')->name('production-orders.items.update');
        Route::put('production-orders/{productionOrder}/steps/{step}', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'updateStep'])
            ->middleware('can:atelier.production.manage')->name('production-orders.steps.update');

        // Atölye İş Akışı — kalıpçı/tasarımcı ataması (Modül D)
        Route::get('assignments', [\Modules\Atelier\Http\Controllers\AssignmentController::class, 'index'])
            ->middleware('can:atelier.view')->name('assignments.index');
        Route::post('assignments', [\Modules\Atelier\Http\Controllers\AssignmentController::class, 'store'])
            ->middleware('can:atelier.assignment.manage')->name('assignments.store');
        // Başla/teslim: atanan kişi de yapabilir → yetki controller'da kontrol edilir.
        Route::post('assignments/{assignment}/start', [\Modules\Atelier\Http\Controllers\AssignmentController::class, 'start'])
            ->name('assignments.start');
        Route::post('assignments/{assignment}/deliver', [\Modules\Atelier\Http\Controllers\AssignmentController::class, 'deliver'])
            ->name('assignments.deliver');
        Route::post('assignments/{assignment}/accept', [\Modules\Atelier\Http\Controllers\AssignmentController::class, 'accept'])
            ->middleware('can:atelier.assignment.manage')->name('assignments.accept');
        Route::post('assignments/{assignment}/reject', [\Modules\Atelier\Http\Controllers\AssignmentController::class, 'reject'])
            ->middleware('can:atelier.assignment.manage')->name('assignments.reject');

        // PDF→DXF Sayısallaştırma onay kuyruğu (Kol 1)
        Route::get('conversions', [\Modules\Atelier\Http\Controllers\ConversionController::class, 'index'])
            ->middleware('can:atelier.view')->name('conversions.index');
        Route::post('conversions', [\Modules\Atelier\Http\Controllers\ConversionController::class, 'store'])
            ->middleware('can:atelier.conversion.manage')->name('conversions.store');
        Route::post('conversions/{job}/approve', [\Modules\Atelier\Http\Controllers\ConversionController::class, 'approve'])
            ->middleware('can:atelier.conversion.manage')->name('conversions.approve');
        Route::post('conversions/{job}/reject', [\Modules\Atelier\Http\Controllers\ConversionController::class, 'reject'])
            ->middleware('can:atelier.conversion.manage')->name('conversions.reject');
        Route::post('conversions/{job}/retry', [\Modules\Atelier\Http\Controllers\ConversionController::class, 'retry'])
            ->middleware('can:atelier.conversion.manage')->name('conversions.retry');

        // Kalıp Kütüphanesi (DXF arşivi — Modül C)
        Route::get('patterns', [\Modules\Atelier\Http\Controllers\PatternController::class, 'index'])
            ->middleware('can:atelier.pattern.view')->name('patterns.index');
        Route::post('patterns', [\Modules\Atelier\Http\Controllers\PatternController::class, 'store'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.store');
        Route::post('patterns/import', [\Modules\Atelier\Http\Controllers\PatternController::class, 'importPdf'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.import');
        Route::post('patterns/{pattern}/retry-extraction', [\Modules\Atelier\Http\Controllers\PatternController::class, 'retryExtraction'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.retry-extraction');
        Route::get('patterns/{pattern}/tracer', [\Modules\Atelier\Http\Controllers\PatternController::class, 'tracer'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.tracer');
        Route::get('patterns/{pattern}/tracer-image', [\Modules\Atelier\Http\Controllers\PatternController::class, 'tracerImage'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.tracer-image');
        Route::post('patterns/{pattern}/traced', [\Modules\Atelier\Http\Controllers\PatternController::class, 'saveTraced'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.traced');
        Route::put('patterns/{pattern}', [\Modules\Atelier\Http\Controllers\PatternController::class, 'update'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.update');
        Route::delete('patterns/{pattern}', [\Modules\Atelier\Http\Controllers\PatternController::class, 'destroy'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.destroy');

        // AI Konsept Stüdyosu (kalıp platformu — Kol 2)
        Route::get('concepts', [\Modules\Atelier\Http\Controllers\ConceptStudioController::class, 'index'])
            ->middleware('can:atelier.view')->name('concepts.index');
        Route::post('concepts', [\Modules\Atelier\Http\Controllers\ConceptStudioController::class, 'generate'])
            ->middleware('can:atelier.design.manage')->name('concepts.generate');
        Route::post('concepts/{card}/regenerate', [\Modules\Atelier\Http\Controllers\ConceptStudioController::class, 'regenerate'])
            ->middleware('can:atelier.design.manage')->name('concepts.regenerate');
        Route::post('concepts/{card}/match', [\Modules\Atelier\Http\Controllers\ConceptStudioController::class, 'match'])
            ->middleware('can:atelier.design.manage')->name('concepts.match');
        Route::delete('concepts/{card}', [\Modules\Atelier\Http\Controllers\ConceptStudioController::class, 'destroy'])
            ->middleware('can:atelier.design.manage')->name('concepts.destroy');
    });
