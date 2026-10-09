<?php

use App\Models\User;
use App\Modules\Editor\Models\ChapterDraft;
use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Volume;
use App\Modules\Library\Models\Work;

function sampleDoc(string $text = 'Привет мир'): array
{
    return [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => $text],
                ],
            ],
        ],
    ];
}

it('lazy-creates an empty draft for the owner', function (): void {
    $author = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id]);
    $chapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1]);

    $payload = $this->withToken($token)
        ->getJson('/api/v1/chapters/'.$chapter->id.'/draft')
        ->assertOk()
        ->json('data');

    expect($payload['chapter_id'])->toBe($chapter->id)
        ->and($payload['version'])->toBe(1)
        ->and($payload['content'])->toBe(config('editor.empty_document'))
        ->and($payload['stats']['characters'])->toBe(0)
        ->and($payload['stats']['words'])->toBe(0);

    expect(ChapterDraft::query()->whereKey($chapter->id)->exists())->toBeTrue();
});

it('returns 404 for foreign chapter drafts', function (): void {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $token = $other->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id]);
    $chapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1]);

    auth()->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/v1/chapters/'.$chapter->id.'/draft')
        ->assertNotFound();
});

it('saves drafts with CAS and unicode stats', function (): void {
    $author = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id]);
    $chapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1]);

    $this->withToken($token)->getJson('/api/v1/chapters/'.$chapter->id.'/draft')->assertOk();

    $saved = $this->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
            'content' => sampleDoc('Привет мир'),
            'version' => 1,
        ])
        ->assertOk()
        ->json('data');

    expect($saved['version'])->toBe(2)
        ->and($saved['stats']['characters'])->toBe(mb_strlen('Привет мир'))
        ->and($saved['stats']['words'])->toBe(2);

    $this->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
            'content' => sampleDoc('stale'),
            'version' => 1,
        ])
        ->assertConflict()
        ->assertJsonPath('version', 2);

    expect(ChapterDraft::query()->find($chapter->id)?->plain_text)->toBe('Привет мир');
});

it('allows concurrent first create then CAS conflict on same version', function (): void {
    $author = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id]);
    $chapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1]);

    $this->withToken($token)->getJson('/api/v1/chapters/'.$chapter->id.'/draft')->assertOk();
    $this->withToken($token)->getJson('/api/v1/chapters/'.$chapter->id.'/draft')->assertOk();

    expect(ChapterDraft::query()->where('chapter_id', $chapter->id)->count())->toBe(1);

    $ok = $this->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
            'content' => sampleDoc('first'),
            'version' => 1,
        ]);

    $conflict = $this->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
            'content' => sampleDoc('second'),
            'version' => 1,
        ]);

    expect($ok->status())->toBe(200)
        ->and($conflict->status())->toBe(409);

    $affected = ChapterDraft::query()
        ->where('chapter_id', $chapter->id)
        ->where('version', 2)
        ->update([
            'content_json' => sampleDoc('race'),
            'plain_text' => 'race',
            'version' => 3,
            'updated_at' => now(),
        ]);

    $lost = ChapterDraft::query()
        ->where('chapter_id', $chapter->id)
        ->where('version', 2)
        ->update([
            'content_json' => sampleDoc('lost'),
            'plain_text' => 'lost',
            'version' => 3,
            'updated_at' => now(),
        ]);

    expect($affected)->toBe(1)->and($lost)->toBe(0);
});

it('rejects invalid documents with 422', function (): void {
    $author = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id]);
    $chapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1]);

    $this->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
            'content' => ['type' => 'paragraph', 'content' => []],
            'version' => 1,
        ])
        ->assertUnprocessable();
});

it('preserves whitespace inside ProseMirror text nodes', function (): void {
    $author = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id]);
    $chapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1]);

    $this->withToken($token)->getJson('/api/v1/chapters/'.$chapter->id.'/draft')->assertOk();

    $spaces = '   ';
    $saved = $this->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
            'content' => sampleDoc($spaces),
            'version' => 1,
        ])
        ->assertOk()
        ->json('data');

    expect($saved['content']['content'][0]['content'][0]['text'])->toBe($spaces)
        ->and(ChapterDraft::query()->find($chapter->id)?->content_json['content'][0]['content'][0]['text'])
        ->toBe($spaces);
});

it('blocks chapter and work delete when editor data exists and allows empty deletes', function (): void {
    $author = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id]);
    $empty = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1, 'title' => 'Empty']);
    $withDraft = Chapter::factory()->create(['work_id' => $work->id, 'position' => 2, 'title' => 'Drafted']);

    $this->withToken($token)->getJson('/api/v1/chapters/'.$withDraft->id.'/draft')->assertOk();

    $this->withToken($token)
        ->deleteJson('/api/v1/works/'.$work->id.'/chapters/'.$withDraft->id)
        ->assertConflict();

    expect(Chapter::query()->whereKey($withDraft->id)->exists())->toBeTrue();

    $this->withToken($token)
        ->deleteJson('/api/v1/works/'.$work->id.'/chapters/'.$empty->id)
        ->assertNoContent();

    expect(Chapter::query()->whereKey($empty->id)->exists())->toBeFalse();

    $this->withToken($token)
        ->deleteJson('/api/v1/works/'.$work->id)
        ->assertConflict();

    expect(Work::query()->whereKey($work->id)->exists())->toBeTrue()
        ->and(ChapterDraft::query()->whereKey($withDraft->id)->exists())->toBeTrue();
});

it('keeps chapters when deleting a volume that has drafts', function (): void {
    $author = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id]);
    $volume = Volume::factory()->create(['work_id' => $work->id, 'position' => 1]);
    $chapter = Chapter::factory()->create([
        'work_id' => $work->id,
        'volume_id' => $volume->id,
        'position' => 1,
    ]);

    $this->withToken($token)->getJson('/api/v1/chapters/'.$chapter->id.'/draft')->assertOk();

    $this->withToken($token)
        ->deleteJson('/api/v1/works/'.$work->id.'/volumes/'.$volume->id)
        ->assertNoContent();

    $chapter->refresh();

    expect($chapter->volume_id)->toBeNull()
        ->and(ChapterDraft::query()->whereKey($chapter->id)->exists())->toBeTrue();
});
