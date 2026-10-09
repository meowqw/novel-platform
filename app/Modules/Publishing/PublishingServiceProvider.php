<?php

namespace App\Modules\Publishing;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PublishingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        RateLimiter::for('publishing-write', function (Request $request) {
            return Limit::perMinute(60)->by((string) $request->user()?->id);
        });

        RateLimiter::for('publishing-public', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip() ?? 'unknown');
        });

        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/routes.php');
    }
}
