<?php

use App\Models\User;
use App\Modules\Identity\Notifications\ResetPasswordNotification;
use App\Modules\Identity\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('accepts forgot password without revealing account existence', function (): void {
    Notification::fake();

    User::factory()->create(['email' => 'known@example.com']);

    $known = $this->postJson('/api/v1/auth/password/forgot', [
        'email' => 'known@example.com',
    ])->assertOk();

    $unknown = $this->postJson('/api/v1/auth/password/forgot', [
        'email' => 'unknown@example.com',
    ])->assertOk();

    expect($known->json('message'))->toBe($unknown->json('message'));
    Notification::assertSentTo(User::query()->where('email', 'known@example.com')->first(), ResetPasswordNotification::class);
});

it('resets a password with a valid token and revokes all api tokens', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'reset@example.com',
        'password' => 'password12345',
    ]);
    $user->createToken('one');
    $user->createToken('two');

    $token = Password::createToken($user);

    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'reset@example.com',
        'token' => $token,
        'password' => 'newpassword1234',
        'password_confirmation' => 'newpassword1234',
    ])->assertOk();

    expect(Hash::check('newpassword1234', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);

    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'reset@example.com',
        'token' => 'invalid-token',
        'password' => 'newpassword1234',
        'password_confirmation' => 'newpassword1234',
    ])->assertUnprocessable();
});

it('changes password keeping the current api token and revoking others', function (): void {
    $user = User::factory()->create([
        'password' => 'password12345',
    ]);
    $current = $user->createToken('current')->plainTextToken;
    $other = $user->createToken('other')->plainTextToken;

    $this->withToken($current)
        ->putJson('/api/v1/auth/password', [
            'current_password' => 'password12345',
            'password' => 'newerpassword12',
            'password_confirmation' => 'newerpassword12',
        ])
        ->assertOk();

    expect($user->tokens()->count())->toBe(1);

    auth()->forgetGuards();

    $this->withToken($current)
        ->getJson('/api/v1/auth/me')
        ->assertOk();

    auth()->forgetGuards();

    $this->withToken($other)
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});

it('changes email and resets verification status', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'old@example.com',
        'password' => 'password12345',
        'email_verified_at' => now(),
    ]);
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/v1/auth/email', [
            'current_password' => 'password12345',
            'email' => 'New@Example.com',
        ])
        ->assertOk()
        ->assertJsonPath('data.email', 'new@example.com')
        ->assertJsonPath('data.email_verified_at', null);

    Notification::assertSentTo($user->fresh(), VerifyEmailNotification::class);
});
