<?php

use Illuminate\Support\Facades\Route;
use Modules\PatternEngine\Http\Controllers\PatternEngineController;
use Modules\PatternEngine\Http\Controllers\PatternProfileController;

Route::middleware(['auth', 'verified'])
    ->prefix('pattern-engine')
    ->name('patternengine.')
    ->group(function () {
        Route::get('/', [PatternEngineController::class, 'index'])
            ->middleware('can:patternengine.manage')->name('index');
        Route::post('/generate', [PatternEngineController::class, 'store'])
            ->middleware('can:patternengine.manage')->name('generate');
        Route::get('/jobs/{job}/download', [PatternEngineController::class, 'download'])
            ->middleware('can:patternengine.manage')->name('jobs.download');
        Route::post('/digitize', [PatternEngineController::class, 'digitize'])
            ->middleware('can:patternengine.manage')->name('digitize');

        Route::get('/profiles', [PatternProfileController::class, 'index'])
            ->middleware('can:patternengine.profile.manage')->name('profiles.index');
        Route::post('/profiles', [PatternProfileController::class, 'store'])
            ->middleware('can:patternengine.profile.manage')->name('profiles.store');
    });
