<?php

use Illuminate\Support\Facades\Route;
use Modules\Atelier\Http\Controllers\AtelierController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('ateliers', AtelierController::class)->names('atelier');
});
