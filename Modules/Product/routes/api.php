<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Http\Controllers\ProductController;

// Web tarafıyla aynı izin granülerliği: okuma product.view, yazma product.add, silme product.delete.
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('products', [ProductController::class, 'index'])
        ->middleware('can:product.view')->name('product.index');
    Route::get('products/{product}', [ProductController::class, 'show'])
        ->whereNumber('product')->middleware('can:product.view')->name('product.show');
    Route::post('products', [ProductController::class, 'store'])
        ->middleware('can:product.add')->name('product.store');
    Route::put('products/{product}', [ProductController::class, 'update'])
        ->whereNumber('product')->middleware('can:product.add')->name('product.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])
        ->whereNumber('product')->middleware('can:product.delete')->name('product.destroy');
});
