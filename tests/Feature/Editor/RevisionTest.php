<?php

use App\Models\User;
use App\Modules\Editor\Models\ChapterDraft;
use App\Modules\Editor\Models\ChapterRevision;
use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Work;

function revisionDoc(string $text): array
{
    return [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => $text]],
            ],
        ],
    ];
}

it('creates lists and restores revisions with CAS', function (): void {
    $author = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id]);
    $chapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1]);

    $this->withToken($token)->getJson('/api/v1/chapters/'.$chapter->id.'/draft')->assertOk();

    $this->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
            'content' => revisionDoc('v1 text'),
            'version' => 1,
        ])
        ->assertOk();

    $revision = $this->withToken($token)
        ->postJson('/api/v1/chapters/'.$chapter->id.'/revisions', [
            'label' => 'checkpoint',
        ])
        ->assertCreated()
        ->json('data');

    expect($revision['source_version'])->toBe(2)
        ->and($revision['label'])->toBe('checkpoint')
        ->and($revision['content']['content'][0]['content'][0]['text'])->toBe('v1 text');

    $this->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
            'content' => revisionDoc('changed'),
            'version' => 2,
        ])
        ->assertOk();

    $list = $this->withToken($token)
        ->getJson('/api/v1/chapters/'.$chapter->id.'/revisions')
        ->assertOk()
        ->json('data');

    expect($list)->toHaveCount(1)
        ->and($list[0])->not->toHaveKey('content')
        ->and($list[0]['id'])->toBe($revision['id']);

    $restored = $this->withToken($token)
        ->postJson('/api/v1/chapters/'.$chapter->id.'/revisions/'.$revision['id'].'/restore', [
            'version' => 3,
        ])
        ->assertOk()
        ->json('data');

    expect($restored['version'])->toBe(4)
        ->and($restored['content']['content'][0]['content'][0]['text'])->toBe('v1 text');

    expect(ChapterRevision::query()->find($revision['id'])?->plain_text)->toBe('v1 text');

    $this->withToken($token)
        ->postJson('/api/v1/chapters/'.$chapter->id.'/revisions/'.$revision['id'].'/restore', [
            'version' => 3,
        ])
        ->assertConflict();
});

it('honours Idempotency-Key and rejects payload reuse conflicts', function (): void {
    $author = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id]);
    $chapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1]);

    $this->withToken($token)->getJson('/api/v1/chapters/'.$chapter->id.'/draft')->assertOk();
    $this->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
            'content' => revisionDoc('snap'),
            'version' => 1,
        ])
        ->assertOk();

    $first = $this->withToken($token)
        ->withHeaders(['Idempotency-Key' => 'rev-1'])
        ->postJson('/api/v1/chapters/'.$chapter->id.'/revisions', ['label' => 'A'])
        ->assertCreated()
        ->json('data');

    $second = $this->withToken($token)
        ->withHeaders(['Idempotency-Key' => 'rev-1'])
        ->postJson('/api/v1/chapters/'.$chapter->id.'/revisions', ['label' => 'A'])
        ->assertCreated()
        ->json('data');

    expect($second['id'])->toBe($first['id'])
        ->and(ChapterRevision::query()->where('chapter_id', $chapter->id)->count())->toBe(1);

    $this->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
            'content' => revisionDoc('next'),
            'version' => 2,
        ])
        ->assertOk();

    $this->withToken($token)
        ->withHeaders(['Idempotency-Key' => 'rev-1'])
        ->postJson('/api/v1/chapters/'.$chapter->id.'/revisions', ['label' => 'A'])
        ->assertConflict();

    $this->withToken($token)
        ->withHeaders(['Idempotency-Key' => 'rev-1'])
        ->postJson('/api/v1/chapters/'.$chapter->id.'/revisions', ['label' => 'B'])
        ->assertConflict();
});

it('returns 404 for foreign revisions and mismatched chapter scope', function (): void {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $authorToken = $author->createToken('api')->plainTextToken;
    $otherToken = $other->createToken('api')->plainTextToken;

    $work = Work::factory()->create(['author_id' => $author->id]);
    $chapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1]);
    $otherChapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 2]);

    $this->withToken($authorToken)->getJson('/api/v1/chapters/'.$chapter->id.'/draft')->assertOk();
    $revision = $this->withToken($authorToken)
        ->postJson('/api/v1/chapters/'.$chapter->id.'/revisions')
        ->assertCreated()
        ->json('data');

    auth()->forgetGuards();

    $this->withToken($otherToken)
        ->getJson('/api/v1/chapters/'.$chapter->id.'/revisions/'.$revision['id'])
        ->assertNotFound();

    auth()->forgetGuards();

    $this->withToken($authorToken)
        ->getJson('/api/v1/chapters/'.$otherChapter->id.'/revisions/'.$revision['id'])
        ->assertNotFound();
});

it('does not mutate revisions after creation', function (): void {
    $author = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id]);
    $chapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1]);

    $this->withToken($token)->getJson('/api/v1/chapters/'.$chapter->id.'/draft')->assertOk();
    $this->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
            'content' => revisionDoc('immutable'),
            'version' => 1,
        ])
        ->assertOk();

    $revision = $this->withToken($token)
        ->postJson('/api/v1/chapters/'.$chapter->id.'/revisions')
        ->assertCreated()
        ->json('data');

    $this->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
            'content' => revisionDoc('later'),
            'version' => 2,
        ])
        ->assertOk();

    $fetched = $this->withToken($token)
        ->getJson('/api/v1/chapters/'.$chapter->id.'/revisions/'.$revision['id'])
        ->assertOk()
        ->json('data');

    expect($fetched['content']['content'][0]['content'][0]['text'])->toBe('immutable')
        ->and(ChapterDraft::query()->find($chapter->id)?->plain_text)->toBe('later');
});
