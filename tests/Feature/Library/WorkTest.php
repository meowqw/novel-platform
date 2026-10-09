<?php

use App\Models\User;
use App\Modules\Library\Models\Work;

it('rejects unauthenticated library access', function (): void {
    $this->getJson('/api/v1/works')->assertUnauthorized();
});

it('allows an author to manage own works and hides foreign works', function (): void {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;

    $created = $this->withToken($token)
        ->postJson('/api/v1/works', [
            'title' => 'Мой роман',
            'description' => 'Desc',
            'status' => 'ongoing',
        ])
        ->assertCreated()
        ->json('data');

    expect($created['slug'])->not->toBeEmpty()
        ->and($created['title'])->toBe('Мой роман');

    $this->withToken($token)
        ->patchJson('/api/v1/works/'.$created['id'], [
            'title' => 'Новое название',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Новое название')
        ->assertJsonPath('data.slug', $created['slug']);

    $this->withToken($token)
        ->patchJson('/api/v1/works/'.$created['id'], [
            'slug' => 'custom-slug',
        ])
        ->assertOk()
        ->assertJsonPath('data.slug', 'custom-slug');

    $foreign = Work::factory()->create(['author_id' => $other->id]);

    $this->withToken($token)
        ->getJson('/api/v1/works/'.$foreign->id)
        ->assertNotFound();

    $this->withToken($token)
        ->getJson('/api/v1/works')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->withToken($token)
        ->deleteJson('/api/v1/works/'.$created['id'])
        ->assertNoContent();
});
