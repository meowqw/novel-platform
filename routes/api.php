<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', HealthController::class)
    ->name('api.v1.health');
