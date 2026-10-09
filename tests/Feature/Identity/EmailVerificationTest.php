<?php

use App\Models\User;
use App\Modules\Identity\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('sends and accepts a signed email verification link', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertOk();

    Notification::assertSentTo($user, VerifyEmailNotification::class);

    $url = URL::temporarySignedRoute(
        'api.v1.auth.email.verify',
        now()->addMinutes(60),
        [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ],
    );

    $this->getJson($url)
        ->assertOk()
        ->assertJsonPath('message', __('Email verified.'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects an invalid or expired verification signature', function (): void {
    $user = User::factory()->unverified()->create();

    $invalid = URL::temporarySignedRoute(
        'api.v1.auth.email.verify',
        now()->addMinutes(60),
        [
            'id' => $user->id,
            'hash' => sha1('wrong@example.com'),
        ],
    );

    $this->getJson($invalid)->assertForbidden();

    $expired = URL::temporarySignedRoute(
        'api.v1.auth.email.verify',
        now()->subMinutes(1),
        [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ],
    );

    $this->getJson($expired)->assertForbidden();
});
