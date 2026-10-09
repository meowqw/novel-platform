<?php

use App\Models\User;
use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Volume;
use App\Modules\Library\Models\Work;

it('supports optional volumes and nested ownership checks', function (): void {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;

    $work = Work::factory()->create(['author_id' => $author->id]);
    $foreignWork = Work::factory()->create(['author_id' => $other->id]);
    $foreignVolume = Volume::factory()->create(['work_id' => $foreignWork->id, 'position' => 1]);

    $ungrouped = $this->withToken($token)
        ->postJson('/api/v1/works/'.$work->id.'/chapters', [
            'title' => 'Prologue',
        ])
        ->assertCreated()
        ->json('data');

    expect($ungrouped['volume_id'])->toBeNull()
        ->and($ungrouped['position'])->toBe(1);

    $volume = $this->withToken($token)
        ->postJson('/api/v1/works/'.$work->id.'/volumes', [
            'title' => 'Volume 1',
        ])
        ->assertCreated()
        ->json('data');

    $chapter = $this->withToken($token)
        ->postJson('/api/v1/works/'.$work->id.'/chapters', [
            'title' => 'Chapter 1',
            'volume_id' => $volume['id'],
        ])
        ->assertCreated()
        ->json('data');

    expect($chapter['volume_id'])->toBe($volume['id'])
        ->and($chapter['position'])->toBe(1);

    $this->withToken($token)
        ->postJson('/api/v1/works/'.$work->id.'/chapters', [
            'title' => 'Bad',
            'volume_id' => $foreignVolume->id,
        ])
        ->assertUnprocessable();

    $this->withToken($token)
        ->patchJson('/api/v1/works/'.$work->id.'/volumes/'.$foreignVolume->id, [
            'title' => 'Hack',
        ])
        ->assertNotFound();
});

it('deletes a volume and appends its chapters to ungrouped in order', function (): void {
    $author = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id]);

    Chapter::factory()->create(['work_id' => $work->id, 'volume_id' => null, 'title' => 'U1', 'position' => 1]);

    $volume = Volume::factory()->create(['work_id' => $work->id, 'position' => 1]);
    $c1 = Chapter::factory()->create(['work_id' => $work->id, 'volume_id' => $volume->id, 'title' => 'V1', 'position' => 1]);
    $c2 = Chapter::factory()->create(['work_id' => $work->id, 'volume_id' => $volume->id, 'title' => 'V2', 'position' => 2]);

    $this->withToken($token)
        ->deleteJson('/api/v1/works/'.$work->id.'/volumes/'.$volume->id)
        ->assertNoContent();

    expect(Volume::query()->whereKey($volume->id)->exists())->toBeFalse();

    $ungrouped = Chapter::query()
        ->where('work_id', $work->id)
        ->whereNull('volume_id')
        ->orderBy('position')
        ->pluck('title')
        ->all();

    expect($ungrouped)->toBe(['U1', 'V1', 'V2']);

    expect($c1->fresh()->position)->toBe(2)
        ->and($c2->fresh()->position)->toBe(3);
});
