<?php

use App\Models\User;
use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Work;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Models\PublishedMediaReference;
use App\Modules\Publishing\Models\WorkPublication;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestImages;

function uploadKind(User $user, string $kind): array
{
    return test()->actingAs($user)
        ->post('/api/v1/media/images', [
            'kind' => $kind,
            'file' => TestImages::jpeg(200, 300),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->json('data');
}

it('assigns replaces and clears cover without deleting bytes; live public cover policy', function (): void {
    Storage::fake('media');
    config(['media.disk' => 'media']);

    $author = User::factory()->create();
    $work = Work::factory()->create(['author_id' => $author->id]);

    $coverA = uploadKind($author, 'cover');
    $coverB = uploadKind($author, 'cover');

    $coverPayload = $this->actingAs($author)
        ->putJson('/api/v1/works/'.$work->id.'/cover', ['media_id' => $coverA['id']])
        ->assertOk()
        ->json('data');

    expect($coverPayload['cover_media_id'])->toBe($coverA['id'])
        ->and($coverPayload['cover_url'])->toBeString()
        ->and($coverPayload['cover_url'])->not->toBeEmpty();

    WorkPublication::query()->create([
        'work_id' => $work->id,
        'is_published' => true,
        'published_at' => now(),
    ]);

    $public = $this->getJson('/api/v1/public/works/'.$work->id)->assertOk()->json('data');
    expect($public['cover_url'])->toContain('/public/media/'.$coverA['id'])
        ->and($public['cover_url'])->not->toContain('variant=thumb');

    $catalog = $this->getJson('/api/v1/public/works')->assertOk()->json('data.0');
    expect($catalog['cover_url'])->toContain('variant=thumb');

    $this->get('/api/v1/public/media/'.$coverA['id'])->assertOk();
    $this->get('/api/v1/public/media/'.$coverA['id'].'?variant=thumb')->assertOk();

    $this->actingAs($author)
        ->putJson('/api/v1/works/'.$work->id.'/cover', ['media_id' => $coverB['id']])
        ->assertOk();

    $this->get('/api/v1/public/media/'.$coverB['id'])->assertOk();
    $this->get('/api/v1/public/media/'.$coverA['id'])->assertNotFound();
    expect(MediaAsset::query()->whereKey($coverA['id'])->exists())->toBeTrue();

    $this->actingAs($author)->deleteJson('/api/v1/works/'.$work->id.'/cover')->assertNoContent();
    $this->get('/api/v1/public/media/'.$coverB['id'])->assertNotFound();

    WorkPublication::query()->whereKey($work->id)->update(['is_published' => false]);
    $this->getJson('/api/v1/public/works/'.$work->id)->assertNotFound();
});

it('rejects foreign cover assignment and wrong kind', function (): void {
    Storage::fake('media');
    config(['media.disk' => 'media']);

    $author = User::factory()->create();
    $other = User::factory()->create();
    $work = Work::factory()->create(['author_id' => $author->id]);

    $foreignCover = uploadKind($other, 'cover');
    $chapterImage = uploadKind($author, 'chapter_image');

    $this->actingAs($author)
        ->putJson('/api/v1/works/'.$work->id.'/cover', ['media_id' => $foreignCover['id']])
        ->assertUnprocessable();

    $this->actingAs($author)
        ->putJson('/api/v1/works/'.$work->id.'/cover', ['media_id' => $chapterImage['id']])
        ->assertUnprocessable();
});

it('publishes chapter images publicly and revokes on unpublish; draft-only stays private', function (): void {
    Storage::fake('media');
    config(['media.disk' => 'media']);

    $author = User::factory()->create();
    $work = Work::factory()->create(['author_id' => $author->id]);
    $chapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1]);

    $image = uploadKind($author, 'chapter_image');

    $this->actingAs($author)->getJson('/api/v1/chapters/'.$chapter->id.'/draft')->assertOk();
    $this->actingAs($author)->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
        'version' => 1,
        'content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'Текст с иллюстрацией']],
                ],
                [
                    'type' => 'image',
                    'attrs' => [
                        'mediaId' => $image['id'],
                        'alt' => 'иллюстрация',
                        'width' => 'wide',
                    ],
                ],
            ],
        ],
    ])->assertOk();

    $draftMediaUrl = $this->actingAs($author)
        ->getJson('/api/v1/chapters/'.$chapter->id.'/draft')
        ->json('data.media.'.$image['id'].'.url');
    expect($draftMediaUrl)->toBeString()->not->toBeEmpty();

    $this->get('/api/v1/public/media/'.$image['id'])->assertNotFound();

    $this->actingAs($author)->postJson('/api/v1/chapters/'.$chapter->id.'/publish')->assertOk();
    WorkPublication::query()->create([
        'work_id' => $work->id,
        'is_published' => true,
        'published_at' => now(),
    ]);

    expect(PublishedMediaReference::query()->where('media_id', $image['id'])->exists())->toBeTrue();

    $publicChapter = $this->getJson('/api/v1/public/chapters/'.$chapter->id)->assertOk()->json('data');
    expect($publicChapter['media'])->toHaveKey($image['id'])
        ->and($publicChapter['content']['content'][1]['attrs'])->not->toHaveKey('src');

    $cache = $this->get('/api/v1/public/media/'.$image['id'])
        ->assertOk()
        ->headers
        ->get('Cache-Control');
    expect($cache)->toContain('max-age=60')->toContain('must-revalidate');

    $this->actingAs($author)->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
        'version' => 2,
        'content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'Без картинки']],
                ],
            ],
        ],
    ])->assertOk();

    $this->get('/api/v1/public/media/'.$image['id'])->assertOk();
    expect(MediaAsset::query()->whereKey($image['id'])->exists())->toBeTrue();

    $this->actingAs($author)->postJson('/api/v1/chapters/'.$chapter->id.'/unpublish')->assertOk();
    expect(PublishedMediaReference::query()->where('chapter_id', $chapter->id)->count())->toBe(0);
    $this->get('/api/v1/public/media/'.$image['id'])->assertNotFound();

    $this->actingAs($author)->postJson('/api/v1/chapters/'.$chapter->id.'/publish')->assertOk();
    expect(PublishedMediaReference::query()->where('chapter_id', $chapter->id)->count())->toBe(0);
    $this->get('/api/v1/public/media/'.$image['id'])->assertNotFound();
});

it('keeps chapter media private when only work is unpublished', function (): void {
    Storage::fake('media');
    config(['media.disk' => 'media']);

    $author = User::factory()->create();
    $work = Work::factory()->create(['author_id' => $author->id]);
    $chapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1]);
    $image = uploadKind($author, 'chapter_image');

    $this->actingAs($author)->getJson('/api/v1/chapters/'.$chapter->id.'/draft')->assertOk();
    $this->actingAs($author)->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
        'version' => 1,
        'content' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Pub']]],
                ['type' => 'image', 'attrs' => ['mediaId' => $image['id']]],
            ],
        ],
    ])->assertOk();
    $this->actingAs($author)->postJson('/api/v1/chapters/'.$chapter->id.'/publish')->assertOk();
    $this->actingAs($author)->putJson('/api/v1/works/'.$work->id.'/publication', ['published' => true])->assertOk();

    $this->get('/api/v1/public/media/'.$image['id'])->assertOk();

    $this->actingAs($author)->putJson('/api/v1/works/'.$work->id.'/publication', ['published' => false])->assertOk();
    $this->get('/api/v1/public/media/'.$image['id'])->assertNotFound();
});
