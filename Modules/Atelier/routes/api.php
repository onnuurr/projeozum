<?php

use Illuminate\Support\Facades\Route;
use Modules\Atelier\Http\Controllers\AtelierController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('ateliers', AtelierController::class)->names('atelier');
});
