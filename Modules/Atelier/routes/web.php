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
    });
