<?php

use App\Models\User;
use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Volume;
use App\Modules\Library\Models\Work;
use Illuminate\Database\QueryException;

it('reorders volumes and chapters contiguously', function (): void {
    $author = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id]);

    $v1 = Volume::factory()->create(['work_id' => $work->id, 'title' => 'A', 'position' => 1]);
    $v2 = Volume::factory()->create(['work_id' => $work->id, 'title' => 'B', 'position' => 2]);
    $v3 = Volume::factory()->create(['work_id' => $work->id, 'title' => 'C', 'position' => 3]);

    $this->withToken($token)
        ->putJson('/api/v1/works/'.$work->id.'/volumes/order', [
            'order' => [$v3->id, $v1->id, $v2->id],
        ])
        ->assertNoContent();

    expect($v3->fresh()->position)->toBe(1)
        ->and($v1->fresh()->position)->toBe(2)
        ->and($v2->fresh()->position)->toBe(3);

    $c1 = Chapter::factory()->create(['work_id' => $work->id, 'volume_id' => null, 'position' => 1]);
    $c2 = Chapter::factory()->create(['work_id' => $work->id, 'volume_id' => null, 'position' => 2]);

    $this->withToken($token)
        ->putJson('/api/v1/works/'.$work->id.'/chapters/order', [
            'volume_id' => null,
            'order' => [$c2->id, $c1->id],
        ])
        ->assertNoContent();

    expect($c2->fresh()->position)->toBe(1)
        ->and($c1->fresh()->position)->toBe(2);
});

it('moves a chapter between volume and ungrouped', function (): void {
    $author = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id]);
    $volume = Volume::factory()->create(['work_id' => $work->id, 'position' => 1]);

    $u1 = Chapter::factory()->create(['work_id' => $work->id, 'volume_id' => null, 'position' => 1]);
    $v1 = Chapter::factory()->create(['work_id' => $work->id, 'volume_id' => $volume->id, 'position' => 1]);
    $v2 = Chapter::factory()->create(['work_id' => $work->id, 'volume_id' => $volume->id, 'position' => 2]);

    $this->withToken($token)
        ->postJson('/api/v1/works/'.$work->id.'/chapters/'.$v2->id.'/move', [
            'volume_id' => null,
            'position' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.volume_id', null)
        ->assertJsonPath('data.position', 1);

    expect($u1->fresh()->position)->toBe(2)
        ->and($v1->fresh()->position)->toBe(1)
        ->and(Chapter::query()->where('volume_id', $volume->id)->count())->toBe(1);
});

it('enforces nulls-not-distinct unique positions at the database level', function (): void {
    $work = Work::factory()->create();

    Chapter::factory()->create([
        'work_id' => $work->id,
        'volume_id' => null,
        'position' => 1,
    ]);

    expect(fn () => Chapter::factory()->create([
        'work_id' => $work->id,
        'volume_id' => null,
        'position' => 1,
    ]))->toThrow(QueryException::class);
});
