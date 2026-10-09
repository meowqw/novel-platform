<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

it('logs in with valid credentials and returns a token', function (): void {
    $user = User::factory()->create([
        'email' => 'reader@example.com',
        'password' => 'password12345',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'READER@example.com',
        'password' => 'password12345',
    ])->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonStructure(['data', 'token']);
});

it('rejects invalid credentials without revealing which field failed', function (): void {
    User::factory()->create([
        'email' => 'reader@example.com',
        'password' => 'password12345',
    ]);

    $missing = $this->postJson('/api/v1/auth/login', [
        'email' => 'missing@example.com',
        'password' => 'password12345',
    ])->assertUnprocessable();

    $wrong = $this->postJson('/api/v1/auth/login', [
        'email' => 'reader@example.com',
        'password' => 'wrong-password-1',
    ])->assertUnprocessable();

    expect($missing->json('errors.email'))->toEqual($wrong->json('errors.email'));
});

it('returns the authenticated user on me and rejects guests', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized();

    $this->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonMissingPath('data.password');
});

it('logs out the current token and can logout all tokens', function (): void {
    $user = User::factory()->create();
    $first = $user->createToken('first')->plainTextToken;
    $second = $user->createToken('second')->plainTextToken;

    $this->withToken($first)
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    // PHPUnit reuses the app; clear cached guard user between requests.
    auth()->forgetGuards();

    $this->withToken($first)
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();

    $this->withToken($second)
        ->getJson('/api/v1/auth/me')
        ->assertOk();

    $this->withToken($second)
        ->postJson('/api/v1/auth/logout-all')
        ->assertNoContent();

    auth()->forgetGuards();

    expect($user->tokens()->count())->toBe(0);
});

it('rate limits login attempts', function (): void {
    $email = 'limited@example.com';
    $ip = '127.0.0.1';
    RateLimiter::clear(md5('auth-login'.$ip.'|'.$email));

    User::factory()->create([
        'email' => $email,
        'password' => 'password12345',
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/auth/login', [
                'email' => $email,
                'password' => 'wrong-password-1',
            ])
            ->assertUnprocessable();
    }

    $this->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'wrong-password-1',
        ])
        ->assertStatus(429);
});

it('does not honour client X-Forwarded-For for auth-register throttle', function (): void {
    $ip = '127.0.0.1';
    RateLimiter::clear(md5('auth-register'.$ip));

    $payload = static fn (string $suffix): array => [
        'username' => 'xff_user_'.$suffix,
        'name' => 'XFF',
        'email' => "xff{$suffix}@example.com",
        'password' => 'password12345',
        'password_confirmation' => 'password12345',
    ];

    for ($i = 0; $i < 5; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/auth/register', $payload((string) $i))
            ->assertCreated();
    }

    $spoofed = $this->withServerVariables([
        'REMOTE_ADDR' => $ip,
        'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
    ])->postJson('/api/v1/auth/register', $payload('spoof'));

    $spoofed->assertStatus(429);
});
