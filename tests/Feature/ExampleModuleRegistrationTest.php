<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

it('registers example module api routes', function (): void {
    expect(Route::has('api.v1.notes.store'))->toBeTrue();
    expect(Route::has('api.v1.notes.show'))->toBeTrue();
});

it('loads the notes migration on a fresh database', function (): void {
    expect(Schema::hasTable('notes'))->toBeTrue();

    $exitCode = Artisan::call('migrate:fresh', ['--force' => true]);

    expect($exitCode)->toBe(0);
    expect(Schema::hasTable('notes'))->toBeTrue();
});
