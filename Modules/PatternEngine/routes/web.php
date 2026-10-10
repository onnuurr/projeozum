<?php

use Illuminate\Support\Facades\Route;
use Modules\PatternEngine\Http\Controllers\AssignmentController;
use Modules\PatternEngine\Http\Controllers\ConceptStudioController;
use Modules\PatternEngine\Http\Controllers\ConversionController;
use Modules\PatternEngine\Http\Controllers\PatternCardController;
use Modules\PatternEngine\Http\Controllers\PatternController;
use Modules\PatternEngine\Http\Controllers\PatternSettingsController;
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

/*
|--------------------------------------------------------------------------
| Kalıp Kütüphanesi / Konsept Stüdyosu / Dönüştürücü / Atama
|--------------------------------------------------------------------------
| 2026-09-22'de Modules/Atelier'den buraya taşındı (bkz.
| docs/superpowers/specs/2026-09-22-modul-calisma-mantigi-revizyonu-design.md §3).
| URL prefix'i ve route adları BİLİNÇLİ OLARAK değiştirilmedi ('atelier.' —
| Atelier'in kendi routes/web.php'sindeki aynı grupla aynı isim alanını
| paylaşıyor) — canlı .env/menü/izin adlarıyla uyumluluk için minimum
| değişiklik tercih edildi.
*/
Route::middleware(['auth', 'verified'])
    ->prefix('atelier')
    ->name('atelier.')
    ->group(function () {
        // Atölye İş Akışı — kalıpçı/tasarımcı ataması (Modül D)
        Route::get('assignments', [AssignmentController::class, 'index'])
            ->middleware('can:atelier.view')->name('assignments.index');
        Route::post('assignments', [AssignmentController::class, 'store'])
            ->middleware('can:atelier.assignment.manage')->name('assignments.store');
        // Başla/teslim: atanan kişi de yapabilir → yetki controller'da kontrol edilir.
        Route::post('assignments/{assignment}/start', [AssignmentController::class, 'start'])
            ->name('assignments.start');
        Route::post('assignments/{assignment}/deliver', [AssignmentController::class, 'deliver'])
            ->name('assignments.deliver');
        Route::post('assignments/{assignment}/accept', [AssignmentController::class, 'accept'])
            ->middleware('can:atelier.assignment.manage')->name('assignments.accept');
        Route::post('assignments/{assignment}/reject', [AssignmentController::class, 'reject'])
            ->middleware('can:atelier.assignment.manage')->name('assignments.reject');

        // PDF→DXF Sayısallaştırma onay kuyruğu (Kol 1)
        Route::get('conversions', [ConversionController::class, 'index'])
            ->middleware('can:atelier.view')->name('conversions.index');
        Route::post('conversions', [ConversionController::class, 'store'])
            ->middleware('can:atelier.conversion.manage')->name('conversions.store');
        Route::post('conversions/{job}/approve', [ConversionController::class, 'approve'])
            ->middleware('can:atelier.conversion.manage')->name('conversions.approve');
        Route::post('conversions/{job}/reject', [ConversionController::class, 'reject'])
            ->middleware('can:atelier.conversion.manage')->name('conversions.reject');
        Route::post('conversions/{job}/retry', [ConversionController::class, 'retry'])
            ->middleware('can:atelier.conversion.manage')->name('conversions.retry');

        // Kalıp Kütüphanesi (DXF arşivi — Modül C)
        Route::get('patterns', [PatternController::class, 'index'])
            ->middleware('can:atelier.pattern.view')->name('patterns.index');
        Route::post('patterns', [PatternController::class, 'store'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.store');
        Route::post('patterns/import', [PatternController::class, 'importPdf'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.import');
        Route::post('patterns/import-url', [PatternController::class, 'importFromUrl'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.import-url');
        Route::post('patterns/import-photo', [PatternController::class, 'importPhoto'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.import-photo');
        Route::post('patterns/{pattern}/retry-extraction', [PatternController::class, 'retryExtraction'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.retry-extraction');
        Route::get('patterns/{pattern}/tracer', [PatternController::class, 'tracer'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.tracer');
        Route::get('patterns/{pattern}/tracer-image', [PatternController::class, 'tracerImage'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.tracer-image');
        Route::post('patterns/{pattern}/traced', [PatternController::class, 'saveTraced'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.traced');
        // Kalıp Ayarları (çark): kalıp tipleri + ölçü noktaları — {pattern}'dan ÖNCE tanımlı olmalı.
        Route::get('patterns/settings', [PatternSettingsController::class, 'index'])
            ->middleware('can:atelier.pattern.settings')->name('patterns.settings.index');
        Route::prefix('patterns/settings')->name('patterns.settings.')->middleware('can:atelier.pattern.settings')->group(function () {
            Route::post('types', [PatternSettingsController::class, 'storeType'])->name('types.store');
            Route::put('types/{type}', [PatternSettingsController::class, 'updateType'])->name('types.update');
            Route::delete('types/{type}', [PatternSettingsController::class, 'destroyType'])->name('types.destroy');
            Route::post('points', [PatternSettingsController::class, 'storePoint'])->name('points.store');
            Route::put('points/{point}', [PatternSettingsController::class, 'updatePoint'])->name('points.update');
            Route::delete('points/{point}', [PatternSettingsController::class, 'destroyPoint'])->name('points.destroy');
        });

        // Kalıp Kartı: ölçü tabloları, parçalar, kumaş sarfiyatı, malzeme
        Route::get('patterns/{pattern}', [PatternCardController::class, 'show'])
            ->middleware('can:atelier.pattern.view')->whereNumber('pattern')->name('patterns.show');
        Route::put('patterns/{pattern}/measurements', [PatternCardController::class, 'saveMeasurements'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.measurements.update');
        Route::put('patterns/{pattern}/production', [PatternCardController::class, 'saveProduction'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.production.update');

        Route::put('patterns/{pattern}', [PatternController::class, 'update'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.update');
        Route::delete('patterns/{pattern}', [PatternController::class, 'destroy'])
            ->middleware('can:atelier.pattern.manage')->name('patterns.destroy');

        // AI Konsept Stüdyosu (kalıp platformu — Kol 2)
        Route::get('concepts', [ConceptStudioController::class, 'index'])
            ->middleware('can:atelier.view')->name('concepts.index');
        Route::post('concepts', [ConceptStudioController::class, 'generate'])
            ->middleware('can:atelier.design.manage')->name('concepts.generate');
        Route::post('concepts/{card}/regenerate', [ConceptStudioController::class, 'regenerate'])
            ->middleware('can:atelier.design.manage')->name('concepts.regenerate');
        Route::post('concepts/{card}/match', [ConceptStudioController::class, 'match'])
            ->middleware('can:atelier.design.manage')->name('concepts.match');
        Route::delete('concepts/{card}', [ConceptStudioController::class, 'destroy'])
            ->middleware('can:atelier.design.manage')->name('concepts.destroy');
    });
