<?php

use App\Modules\Publishing\Http\Controllers\ChapterPublicationController;
use App\Modules\Publishing\Http\Controllers\PublicChapterController;
use App\Modules\Publishing\Http\Controllers\PublicWorkController;
use App\Modules\Publishing\Http\Controllers\WorkPublicationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::middleware('throttle:publishing-public')->group(function (): void {
        Route::get('/public/works/{work}', [PublicWorkController::class, 'show'])
            ->name('public.works.show');
        Route::get('/public/chapters/{chapter}', [PublicChapterController::class, 'show'])
            ->name('public.chapters.show');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/works/{work}/publication', [WorkPublicationController::class, 'show'])
            ->name('works.publication.show');
        Route::put('/works/{work}/publication', [WorkPublicationController::class, 'update'])
            ->middleware('throttle:publishing-write')
            ->name('works.publication.update');

        Route::get('/chapters/{chapter}/publication', [ChapterPublicationController::class, 'show'])
            ->name('chapters.publication.show');
        Route::post('/chapters/{chapter}/publish', [ChapterPublicationController::class, 'publish'])
            ->middleware('throttle:publishing-write')
            ->name('chapters.publish');
        Route::post('/chapters/{chapter}/unpublish', [ChapterPublicationController::class, 'unpublish'])
            ->middleware('throttle:publishing-write')
            ->name('chapters.unpublish');
    });
});
