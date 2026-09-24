<?php

use App\Http\Controllers\HomeDashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Tenant XML feed subdomain rotası web dosyasının en tepesinde tanımlanmalı ki
// domain-siz "/" gibi rotalardan ÖNCE eşleşme kontrolü yapılsın.
// (İlk eşleşen kazanır — bkz. Illuminate\Routing\RouteCollection::matchAgainstRoutes.)
// Not: Bayi Portalı (self-servis) 2026-09-22'de tamamen kaldırıldı — bkz.
// docs/superpowers/specs/2026-09-22-bayi-portali-kaldirma-etki-analizi.md.
// feed.xml token-tabanlı, auth'siz olduğu için ayrı ve kalmaya devam ediyor.
$portalDomain = config('app.portal_domain') ?: env('PORTAL_DOMAIN');
if ($portalDomain) {
    Route::domain('{slug}.'.$portalDomain)
        ->middleware(['throttle:60,1'])
        ->group(base_path('Modules/Tenant/routes/feed.php'));
}

Route::get('/', function () {
    return Inertia::render('Auth/Login', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', HomeDashboardController::class)
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/component-showcase', function () {
        return Inertia::render('ComponentShowcase');
    })->name('component-showcase');

    // Oz kit bileşen vitrini (yeni tasarım dili · resources/js/Components/Oz)
    Route::get('/oz-kit', function () {
        return Inertia::render('OzKit');
    })->name('oz-kit');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

require __DIR__.'/auth.php';
