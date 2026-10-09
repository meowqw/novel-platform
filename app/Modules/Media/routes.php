<?php

use App\Modules\Media\Http\Controllers\MediaDeliveryController;
use App\Modules\Media\Http\Controllers\MediaUploadController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::middleware('throttle:media-public')->group(function (): void {
        Route::get('/public/media/{media}', [MediaDeliveryController::class, 'showPublic'])
            ->name('public.media.show');
    });

    Route::get('/media/{media}/signed', [MediaDeliveryController::class, 'showSigned'])
        ->middleware(['signed', 'throttle:media-public'])
        ->name('media.show.signed');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/media/images', [MediaUploadController::class, 'store'])
            ->middleware('throttle:media-upload')
            ->name('media.images.store');

        Route::get('/media/{media}', [MediaDeliveryController::class, 'showPrivate'])
            ->middleware('throttle:media-private')
            ->name('media.show');
    });
});
