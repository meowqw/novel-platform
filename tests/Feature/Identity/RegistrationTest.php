<?php

use App\Models\User;
use App\Modules\Identity\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    RateLimiter::clear(md5('auth-register'.'127.0.0.1'));
});

it('registers a user and returns a token', function (): void {
    Notification::fake();

    $response = $this->postJson('/api/v1/auth/register', [
        'username' => 'Alice_01',
        'name' => 'Alice',
        'email' => 'Alice@Example.com',
        'password' => 'password12345',
        'password_confirmation' => 'password12345',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.username', 'alice_01')
        ->assertJsonPath('data.email', 'alice@example.com')
        ->assertJsonStructure([
            'data' => ['id', 'username', 'name', 'email', 'email_verified_at', 'bio', 'avatar_url', 'created_at'],
            'token',
        ])
        ->assertJsonMissingPath('data.password');

    $this->assertDatabaseHas('users', [
        'username' => 'alice_01',
        'email' => 'alice@example.com',
    ]);

    Notification::assertSentTo(User::query()->first(), VerifyEmailNotification::class);
});

it('rejects duplicate email and username ignoring case', function (): void {
    User::factory()->create([
        'username' => 'taken_user',
        'email' => 'taken@example.com',
    ]);

    $this->postJson('/api/v1/auth/register', [
        'username' => 'TAKEN_USER',
        'name' => 'Other',
        'email' => 'other@example.com',
        'password' => 'password12345',
        'password_confirmation' => 'password12345',
    ])->assertUnprocessable()->assertJsonValidationErrors(['username']);

    $this->postJson('/api/v1/auth/register', [
        'username' => 'fresh_user',
        'name' => 'Other',
        'email' => 'TAKEN@EXAMPLE.COM',
        'password' => 'password12345',
        'password_confirmation' => 'password12345',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
});

it('rejects reserved usernames and short passwords', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'username' => 'admin',
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'password12345',
        'password_confirmation' => 'password12345',
    ])->assertUnprocessable()->assertJsonValidationErrors(['username']);

    $this->postJson('/api/v1/auth/register', [
        'username' => 'valid_user',
        'name' => 'Valid',
        'email' => 'valid@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
});
