<?php

namespace App\Modules\Media;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        RateLimiter::for('media-upload', function (Request $request) {
            return Limit::perMinute(30)->by((string) $request->user()?->id);
        });

        RateLimiter::for('media-private', function (Request $request) {
            return Limit::perMinute(120)->by((string) $request->user()?->id);
        });

        RateLimiter::for('media-public', function (Request $request) {
            return Limit::perMinute(240)->by($request->ip() ?? 'unknown');
        });

        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/routes.php');
    }
}
