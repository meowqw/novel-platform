<?php

use App\Modules\Catalog\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::middleware('throttle:catalog-public')->group(function (): void {
        Route::get('/public/works', [CatalogController::class, 'index'])
            ->name('public.works.index');
    });
});
