<?php

use Illuminate\Support\Facades\Route;
use Modules\Tenant\Http\Controllers\Portal\PortalDashboardController;

/*
|--------------------------------------------------------------------------
| Tenant Portal Routes
|--------------------------------------------------------------------------
|
| Bu dosya RouteServiceProvider::mapPortalRoutes() tarafından subdomain group
| altında yüklenir:
|
|   Route::domain('{slug}.'.config('app.portal_domain'))
|       ->middleware(['web','auth','verified','tenant.subdomain','can:portal.access'])
|       ->name('portal.')->group(__DIR__.'/portal.php')
|
| Burada middleware tekrar ekleme. Subdomain group zaten verir.
*/

Route::get('/', PortalDashboardController::class)->name('dashboard');

// Phase 1+ controller'lar geldikçe rotalar buraya eklenecek (Plan dosyası §Phase 1).
