<?php

use App\Models\User;
use App\Modules\Library\Models\Work;
use App\Modules\Publishing\Models\WorkPublication;

it('publishes and unpublishes a work idempotently for the owner', function (): void {
    $author = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id]);

    $this->withToken($token)
        ->getJson('/api/v1/works/'.$work->id.'/publication')
        ->assertOk()
        ->assertJsonPath('data.is_published', false)
        ->assertJsonPath('data.published_chapters_count', 0);

    expect(WorkPublication::query()->whereKey($work->id)->exists())->toBeFalse();

    $this->withToken($token)
        ->putJson('/api/v1/works/'.$work->id.'/publication', ['published' => true])
        ->assertOk()
        ->assertJsonPath('data.is_published', true);

    $this->withToken($token)
        ->putJson('/api/v1/works/'.$work->id.'/publication', ['published' => true])
        ->assertOk()
        ->assertJsonPath('data.is_published', true);

    $this->withToken($token)
        ->putJson('/api/v1/works/'.$work->id.'/publication', ['published' => false])
        ->assertOk()
        ->assertJsonPath('data.is_published', false);

    expect(WorkPublication::query()->find($work->id)?->is_published)->toBeFalse();
});

it('returns 404 for foreign work publication and 401 for guests', function (): void {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $work = Work::factory()->create(['author_id' => $author->id]);

    $this->getJson('/api/v1/works/'.$work->id.'/publication')->assertUnauthorized();

    auth()->forgetGuards();

    $this->withToken($other->createToken('api')->plainTextToken)
        ->putJson('/api/v1/works/'.$work->id.'/publication', ['published' => true])
        ->assertNotFound();
});

it('blocks work delete after publication while empty unpublished work remains deletable', function (): void {
    $author = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;
    $empty = Work::factory()->create(['author_id' => $author->id]);
    $published = Work::factory()->create(['author_id' => $author->id]);

    $this->withToken($token)
        ->deleteJson('/api/v1/works/'.$empty->id)
        ->assertNoContent();

    $this->withToken($token)
        ->putJson('/api/v1/works/'.$published->id.'/publication', ['published' => true])
        ->assertOk();

    $this->withToken($token)
        ->putJson('/api/v1/works/'.$published->id.'/publication', ['published' => false])
        ->assertOk();

    $this->withToken($token)
        ->deleteJson('/api/v1/works/'.$published->id)
        ->assertConflict();

    expect(Work::query()->whereKey($published->id)->exists())->toBeTrue();
});
