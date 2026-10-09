<?php

use App\Models\User;
use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Volume;
use App\Modules\Library\Models\Work;
use App\Modules\Publishing\Models\ChapterPublication;

function publicDoc(string $text): array
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

function seedPublishedChapter(string $token, Chapter $chapter, string $text): void
{
    test()->withToken($token)->getJson('/api/v1/chapters/'.$chapter->id.'/draft')->assertOk();
    test()->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
            'content' => publicDoc($text),
            'version' => 1,
        ])
        ->assertOk();
    test()->withToken($token)->postJson('/api/v1/chapters/'.$chapter->id.'/publish')->assertOk();
}

it('exposes only published structure and hides unpublished chapters and empty volumes', function (): void {
    $author = User::factory()->create(['username' => 'author1', 'name' => 'Author One']);
    $token = $author->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id, 'title' => 'Public Work']);

    $emptyVolume = Volume::factory()->create(['work_id' => $work->id, 'position' => 1, 'title' => 'Empty Vol']);
    $volume = Volume::factory()->create(['work_id' => $work->id, 'position' => 2, 'title' => 'Vol 1']);

    $secret = Chapter::factory()->create([
        'work_id' => $work->id,
        'volume_id' => $volume->id,
        'position' => 1,
        'title' => 'Secret Title',
    ]);
    $publicInVolume = Chapter::factory()->create([
        'work_id' => $work->id,
        'volume_id' => $volume->id,
        'position' => 2,
        'title' => 'Visible Chapter',
    ]);
    $ungrouped = Chapter::factory()->create([
        'work_id' => $work->id,
        'volume_id' => null,
        'position' => 1,
        'title' => 'Ungrouped Pub',
    ]);
    $draftOnly = Chapter::factory()->create([
        'work_id' => $work->id,
        'volume_id' => null,
        'position' => 2,
        'title' => 'Draft Only',
    ]);

    seedPublishedChapter($token, $publicInVolume, 'тело');
    seedPublishedChapter($token, $ungrouped, 'ungrouped');

    $this->getJson('/api/v1/public/works/'.$work->id)->assertNotFound();

    $this->withToken($token)
        ->putJson('/api/v1/works/'.$work->id.'/publication', ['published' => true])
        ->assertOk();

    $payload = $this->getJson('/api/v1/public/works/'.$work->id)
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->json('data');

    expect($payload['title'])->toBe('Public Work')
        ->and($payload['author']['username'])->toBe('author1')
        ->and($payload)->not->toHaveKey('email')
        ->and(collect($payload['volumes'])->pluck('title')->all())->toBe(['Vol 1'])
        ->and(collect($payload['volumes'][0]['chapters'])->pluck('title')->all())->toBe(['Visible Chapter'])
        ->and(collect($payload['chapters'])->pluck('title')->all())->toBe(['Ungrouped Pub']);

    $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
    expect($encoded)->not->toContain('Secret Title')
        ->and($encoded)->not->toContain('Draft Only')
        ->and($encoded)->not->toContain('Empty Vol')
        ->and($encoded)->not->toContain('source_draft_version');

    $chapterPayload = $this->getJson('/api/v1/public/chapters/'.$publicInVolume->id)
        ->assertOk()
        ->json('data');

    expect($chapterPayload['content']['content'][0]['content'][0]['text'])->toBe('тело')
        ->and($chapterPayload)->not->toHaveKey('source_draft_version')
        ->and($chapterPayload)->not->toHaveKey('plain_text');

    $this->getJson('/api/v1/public/chapters/'.$secret->id)->assertNotFound();
    $this->getJson('/api/v1/public/chapters/'.$draftOnly->id)->assertNotFound();

    $this->withToken($token)
        ->putJson('/api/v1/works/'.$work->id.'/publication', ['published' => false])
        ->assertOk();

    $this->getJson('/api/v1/public/works/'.$work->id)->assertNotFound();
    $this->getJson('/api/v1/public/chapters/'.$publicInVolume->id)->assertNotFound();

    expect(ChapterPublication::query()->find($publicInVolume->id)?->is_published)->toBeTrue();

    $this->withToken($token)
        ->putJson('/api/v1/works/'.$work->id.'/publication', ['published' => true])
        ->assertOk();

    $this->getJson('/api/v1/public/chapters/'.$publicInVolume->id)->assertOk();
});

it('hides chapter when unpublished even if work stays published', function (): void {
    $author = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id]);
    $chapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1]);

    seedPublishedChapter($token, $chapter, 'visible');
    $this->withToken($token)
        ->putJson('/api/v1/works/'.$work->id.'/publication', ['published' => true])
        ->assertOk();

    $this->getJson('/api/v1/public/chapters/'.$chapter->id)->assertOk();

    $this->withToken($token)
        ->postJson('/api/v1/chapters/'.$chapter->id.'/unpublish')
        ->assertOk();

    $this->getJson('/api/v1/public/chapters/'.$chapter->id)->assertNotFound();
    $this->getJson('/api/v1/public/works/'.$work->id)
        ->assertOk()
        ->assertJsonPath('data.chapters', []);
});
