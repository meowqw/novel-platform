<?php

use App\Models\User;
use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Work;
use App\Modules\Publishing\Models\ChapterPublication;

function publishingDoc(string $text): array
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

it('rejects publish without draft or with whitespace-only text', function (): void {
    $author = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id]);
    $chapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1]);

    $this->withToken($token)
        ->postJson('/api/v1/chapters/'.$chapter->id.'/publish')
        ->assertUnprocessable();

    $this->withToken($token)->getJson('/api/v1/chapters/'.$chapter->id.'/draft')->assertOk();

    $this->withToken($token)
        ->postJson('/api/v1/chapters/'.$chapter->id.'/publish')
        ->assertUnprocessable();

    $this->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
            'content' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'hardBreak'],
                        ],
                    ],
                ],
            ],
            'version' => 1,
        ])
        ->assertOk();

    $this->withToken($token)
        ->postJson('/api/v1/chapters/'.$chapter->id.'/publish')
        ->assertUnprocessable();

    $this->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
            'content' => publishingDoc('   '),
            'version' => 2,
        ])
        ->assertOk();

    $this->withToken($token)
        ->postJson('/api/v1/chapters/'.$chapter->id.'/publish')
        ->assertUnprocessable();
});

it('publishes a chapter snapshot and ignores later draft edits until republish', function (): void {
    $author = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id]);
    $chapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1]);

    $this->withToken($token)->getJson('/api/v1/chapters/'.$chapter->id.'/draft')->assertOk();
    $this->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
            'content' => publishingDoc('Первая версия'),
            'version' => 1,
        ])
        ->assertOk();

    $published = $this->withToken($token)
        ->postJson('/api/v1/chapters/'.$chapter->id.'/publish')
        ->assertOk()
        ->json('data');

    expect($published['is_published'])->toBeTrue()
        ->and($published['source_draft_version'])->toBe(2)
        ->and($published['has_snapshot'])->toBeTrue();

    $this->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
            'content' => publishingDoc('Черновик изменён'),
            'version' => 2,
        ])
        ->assertOk();

    expect(ChapterPublication::query()->find($chapter->id)?->plain_text)->toBe('Первая версия');

    $this->withToken($token)
        ->postJson('/api/v1/chapters/'.$chapter->id.'/publish')
        ->assertOk()
        ->assertJsonPath('data.source_draft_version', 3);

    expect(ChapterPublication::query()->find($chapter->id)?->plain_text)->toBe('Черновик изменён');

    $this->withToken($token)
        ->postJson('/api/v1/chapters/'.$chapter->id.'/unpublish')
        ->assertOk()
        ->assertJsonPath('data.is_published', false)
        ->assertJsonPath('data.has_snapshot', true);

    expect(ChapterPublication::query()->find($chapter->id)?->plain_text)->toBe('Черновик изменён');
});

it('blocks chapter delete after publish and keeps chapter when unpublishing volume', function (): void {
    $author = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id]);
    $empty = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1, 'title' => 'Empty']);
    $chapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 2, 'title' => 'Body']);

    $this->withToken($token)->getJson('/api/v1/chapters/'.$chapter->id.'/draft')->assertOk();
    $this->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
            'content' => publishingDoc('text'),
            'version' => 1,
        ])
        ->assertOk();
    $this->withToken($token)->postJson('/api/v1/chapters/'.$chapter->id.'/publish')->assertOk();

    $this->withToken($token)
        ->deleteJson('/api/v1/works/'.$work->id.'/chapters/'.$chapter->id)
        ->assertConflict();

    $this->withToken($token)
        ->deleteJson('/api/v1/works/'.$work->id.'/chapters/'.$empty->id)
        ->assertNoContent();
});
