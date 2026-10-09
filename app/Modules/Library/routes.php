<?php

use App\Modules\Library\Http\Controllers\ChapterController;
use App\Modules\Library\Http\Controllers\VolumeController;
use App\Modules\Library\Http\Controllers\WorkController;
use App\Modules\Library\Http\Controllers\WorkCoverController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/works', [WorkController::class, 'index'])->name('works.index');
    Route::post('/works', [WorkController::class, 'store'])->name('works.store');
    Route::get('/works/{work}', [WorkController::class, 'show'])->name('works.show');
    Route::patch('/works/{work}', [WorkController::class, 'update'])->name('works.update');
    Route::delete('/works/{work}', [WorkController::class, 'destroy'])->name('works.destroy');

    Route::put('/works/{work}/cover', [WorkCoverController::class, 'update'])->name('works.cover.update');
    Route::delete('/works/{work}/cover', [WorkCoverController::class, 'destroy'])->name('works.cover.destroy');

    Route::put('/works/{work}/volumes/order', [VolumeController::class, 'reorder'])->name('works.volumes.order');
    Route::post('/works/{work}/volumes', [VolumeController::class, 'store'])->name('works.volumes.store');
    Route::patch('/works/{work}/volumes/{volume}', [VolumeController::class, 'update'])->name('works.volumes.update');
    Route::delete('/works/{work}/volumes/{volume}', [VolumeController::class, 'destroy'])->name('works.volumes.destroy');

    Route::put('/works/{work}/chapters/order', [ChapterController::class, 'reorder'])->name('works.chapters.order');
    Route::post('/works/{work}/chapters', [ChapterController::class, 'store'])->name('works.chapters.store');
    Route::post('/works/{work}/chapters/{chapter}/move', [ChapterController::class, 'move'])->name('works.chapters.move');
    Route::patch('/works/{work}/chapters/{chapter}', [ChapterController::class, 'update'])->name('works.chapters.update');
    Route::delete('/works/{work}/chapters/{chapter}', [ChapterController::class, 'destroy'])->name('works.chapters.destroy');
});
