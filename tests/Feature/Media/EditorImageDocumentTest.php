<?php

use App\Models\User;
use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Work;
use App\Modules\Media\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestImages;

function uploadChapterImage(User $user): string
{
    return test()->actingAs($user)
        ->post('/api/v1/media/images', [
            'kind' => 'chapter_image',
            'file' => TestImages::jpeg(100, 100),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->json('data.id');
}

it('accepts valid image nodes and rejects src external attrs wrong owner and cover kind', function (): void {
    Storage::fake('media');
    config(['media.disk' => 'media']);

    $author = User::factory()->create();
    $other = User::factory()->create();
    $work = Work::factory()->create(['author_id' => $author->id]);
    $chapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1]);

    $mediaId = uploadChapterImage($author);
    $foreignId = uploadChapterImage($other);
    $coverId = $this->actingAs($author)
        ->post('/api/v1/media/images', [
            'kind' => 'cover',
            'file' => TestImages::jpeg(100, 100),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($author)->getJson('/api/v1/chapters/'.$chapter->id.'/draft')->assertOk();

    $this->actingAs($author)->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
        'version' => 1,
        'content' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'ok']]],
                ['type' => 'image', 'attrs' => ['mediaId' => $mediaId, 'width' => 'normal']],
            ],
        ],
    ])->assertOk();

    $this->actingAs($author)->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
        'version' => 2,
        'content' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'image', 'attrs' => ['mediaId' => $mediaId, 'src' => 'https://evil.example/x.png']],
            ],
        ],
    ])->assertUnprocessable();

    $this->actingAs($author)->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
        'version' => 2,
        'content' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'image', 'attrs' => ['mediaId' => $foreignId]],
            ],
        ],
    ])->assertUnprocessable();

    $this->actingAs($author)->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
        'version' => 2,
        'content' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'image', 'attrs' => ['mediaId' => $coverId]],
            ],
        ],
    ])->assertUnprocessable();

    $this->actingAs($author)->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
        'version' => 2,
        'content' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'plain']]],
            ],
        ],
    ])->assertOk();
});

it('preserves image nodes through revision restore and re-checks media ownership', function (): void {
    Storage::fake('media');
    config(['media.disk' => 'media']);

    $author = User::factory()->create();
    $work = Work::factory()->create(['author_id' => $author->id]);
    $chapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1]);
    $mediaId = uploadChapterImage($author);

    $this->actingAs($author)->getJson('/api/v1/chapters/'.$chapter->id.'/draft')->assertOk();
    $this->actingAs($author)->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
        'version' => 1,
        'content' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'v1']]],
                ['type' => 'image', 'attrs' => ['mediaId' => $mediaId, 'alt' => 'a']],
            ],
        ],
    ])->assertOk();

    $revision = $this->actingAs($author)
        ->postJson('/api/v1/chapters/'.$chapter->id.'/revisions', ['label' => 'with-image'])
        ->assertCreated()
        ->json('data');

    expect($revision['media'])->toHaveKey($mediaId)
        ->and($revision['content']['content'][1]['attrs']['mediaId'])->toBe($mediaId);

    $detail = $this->actingAs($author)
        ->getJson('/api/v1/chapters/'.$chapter->id.'/revisions/'.$revision['id'])
        ->assertOk()
        ->json('data');

    expect($detail['media'][$mediaId]['url'])->toContain('/media/');

    $this->actingAs($author)->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
        'version' => 2,
        'content' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'later']]],
            ],
        ],
    ])->assertOk();

    $restored = $this->actingAs($author)
        ->postJson('/api/v1/chapters/'.$chapter->id.'/revisions/'.$revision['id'].'/restore', [
            'version' => 3,
        ])
        ->assertOk()
        ->json('data');

    expect($restored['content']['content'][1]['attrs']['mediaId'])->toBe($mediaId)
        ->and($restored['media'])->toHaveKey($mediaId)
        ->and(MediaAsset::query()->whereKey($mediaId)->exists())->toBeTrue();
});

it('rejects image-only publish while allowing text plus image', function (): void {
    Storage::fake('media');
    config(['media.disk' => 'media']);

    $author = User::factory()->create();
    $work = Work::factory()->create(['author_id' => $author->id]);
    $chapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1]);
    $mediaId = uploadChapterImage($author);

    $this->actingAs($author)->getJson('/api/v1/chapters/'.$chapter->id.'/draft')->assertOk();
    $this->actingAs($author)->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
        'version' => 1,
        'content' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'image', 'attrs' => ['mediaId' => $mediaId]],
            ],
        ],
    ])->assertOk();

    $this->actingAs($author)
        ->postJson('/api/v1/chapters/'.$chapter->id.'/publish')
        ->assertUnprocessable();

    $this->actingAs($author)->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
        'version' => 2,
        'content' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Есть текст']]],
                ['type' => 'image', 'attrs' => ['mediaId' => $mediaId]],
            ],
        ],
    ])->assertOk();

    $this->actingAs($author)->postJson('/api/v1/chapters/'.$chapter->id.'/publish')->assertOk();
});
