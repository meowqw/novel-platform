<?php

use App\Modules\Editor\Http\Controllers\DraftController;
use App\Modules\Editor\Http\Controllers\RevisionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/chapters/{chapter}/draft', [DraftController::class, 'show'])
        ->name('chapters.draft.show');
    Route::put('/chapters/{chapter}/draft', [DraftController::class, 'update'])
        ->middleware('throttle:editor-write')
        ->name('chapters.draft.update');

    Route::get('/chapters/{chapter}/revisions', [RevisionController::class, 'index'])
        ->name('chapters.revisions.index');
    Route::post('/chapters/{chapter}/revisions', [RevisionController::class, 'store'])
        ->middleware('throttle:editor-write')
        ->name('chapters.revisions.store');
    Route::get('/chapters/{chapter}/revisions/{revision}', [RevisionController::class, 'show'])
        ->name('chapters.revisions.show');
    Route::post('/chapters/{chapter}/revisions/{revision}/restore', [RevisionController::class, 'restore'])
        ->middleware('throttle:editor-write')
        ->name('chapters.revisions.restore');
});
