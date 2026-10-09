<?php

use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\EmailVerificationController;
use App\Modules\Identity\Http\Controllers\PasswordResetController;
use App\Modules\Identity\Http\Controllers\ProfileController;
use App\Modules\Identity\Http\Controllers\TokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:auth-register')
        ->name('auth.register');

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth-login')
        ->name('auth.login');

    Route::post('/auth/password/forgot', [PasswordResetController::class, 'forgot'])
        ->middleware('throttle:auth-password')
        ->name('auth.password.forgot');

    Route::post('/auth/password/reset', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:auth-password')
        ->name('auth.password.reset');

    Route::get('/auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:auth-verification'])
        ->name('auth.email.verify');

    Route::get('/users/{username}', [ProfileController::class, 'show'])
        ->name('users.show');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');

        Route::get('/auth/tokens', [TokenController::class, 'index'])->name('auth.tokens.index');
        Route::delete('/auth/tokens/{id}', [TokenController::class, 'destroy'])->name('auth.tokens.destroy');

        Route::post('/auth/email/verification-notification', [EmailVerificationController::class, 'notice'])
            ->middleware('throttle:auth-verification')
            ->name('auth.email.verification-notification');

        Route::put('/auth/password', [PasswordResetController::class, 'updatePassword'])
            ->name('auth.password.update');
        Route::put('/auth/email', [PasswordResetController::class, 'updateEmail'])
            ->name('auth.email.update');

        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    });
});
