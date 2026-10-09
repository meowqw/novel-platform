<?php

use App\Models\User;

it('lists tokens without secrets and revokes owned tokens only', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $ownerToken = $owner->createToken('device-a');
    $otherToken = $other->createToken('device-b');

    $plain = $ownerToken->plainTextToken;

    $this->withToken($plain)
        ->getJson('/api/v1/auth/tokens')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'device-a')
        ->assertJsonMissingPath('data.0.token');

    $this->withToken($plain)
        ->deleteJson('/api/v1/auth/tokens/'.$otherToken->accessToken->id)
        ->assertNotFound();

    $this->withToken($plain)
        ->deleteJson('/api/v1/auth/tokens/'.$ownerToken->accessToken->id)
        ->assertNoContent();

    expect($owner->tokens()->count())->toBe(0);
});
