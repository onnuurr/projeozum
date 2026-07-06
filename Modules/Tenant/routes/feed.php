<?php

use Illuminate\Support\Facades\Route;
use Modules\Tenant\Http\Controllers\Portal\TenantFeedController;

/*
|--------------------------------------------------------------------------
| Tenant XML Feed Route
|--------------------------------------------------------------------------
|
| Token-only auth (auth middleware YOK). RouteServiceProvider subdomain
| group içine throttle ile yükler.
*/

Route::get('/feed.xml', [TenantFeedController::class, 'show'])
    ->name('feed');
