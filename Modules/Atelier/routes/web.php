<?php

use Illuminate\Support\Facades\Route;
use Modules\Atelier\Http\Controllers\AtelierController;

Route::middleware(['auth', 'verified', 'role:superadmin', 'can:atelier.manage'])
    ->prefix('atelier')
    ->name('atelier.')
    ->group(function () {
        Route::get('/', [AtelierController::class, 'index'])->name('dashboard');
    });
