<?php

use App\Models\User;

it('shows a public profile without private fields', function (): void {
    $user = User::factory()->create([
        'username' => 'public_author',
        'name' => 'Public Author',
        'bio' => 'Hello',
        'email' => 'private@example.com',
    ]);

    $this->getJson('/api/v1/users/PUBLIC_AUTHOR')
        ->assertOk()
        ->assertJsonPath('data.username', 'public_author')
        ->assertJsonPath('data.bio', 'Hello')
        ->assertJsonMissingPath('data.email')
        ->assertJsonMissingPath('data.password');

    $this->getJson('/api/v1/users/missing_user')
        ->assertNotFound();
});

it('updates the authenticated profile and ignores privileged fields', function (): void {
    $user = User::factory()->create([
        'username' => 'old_name',
        'email_verified_at' => now(),
    ]);
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)
        ->patchJson('/api/v1/profile', [
            'username' => 'new_name',
            'name' => 'New Name',
            'bio' => 'Updated bio',
            'email_verified_at' => null,
            'password' => 'hackerpassword1',
        ])
        ->assertOk()
        ->assertJsonPath('data.username', 'new_name')
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.bio', 'Updated bio');

    $user->refresh();

    expect($user->username)->toBe('new_name')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->not->toBe('hackerpassword1');
});
