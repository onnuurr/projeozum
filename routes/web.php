<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Portal subdomain rotaları web dosyasının en tepesinde tanımlanmalı ki
// domain-siz "/" gibi rotalardan ÖNCE eşleşme kontrolü yapılsın.
// (İlk eşleşen kazanır — bkz. Illuminate\Routing\RouteCollection::matchAgainstRoutes.)
$portalDomain = config('app.portal_domain') ?: env('PORTAL_DOMAIN');
if ($portalDomain) {
    Route::domain('{slug}.'.$portalDomain)
        ->middleware(['throttle:60,1'])
        ->group(base_path('Modules/Tenant/routes/feed.php'));

    Route::domain('{slug}.'.$portalDomain)
        ->middleware(['auth', 'verified', 'active', 'tenant.subdomain', 'can:portal.access'])
        ->name('portal.')
        ->group(base_path('Modules/Tenant/routes/portal.php'));
}

Route::get('/', function () {
    return Inertia::render('Auth/Login', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Workflow');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

require __DIR__.'/auth.php';
