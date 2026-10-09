<?php

use App\Models\User;
use App\Modules\Media\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestImages;

it('uploads cover and chapter images for the owner and rejects guests', function (): void {
    Storage::fake('media');
    config(['media.disk' => 'media']);

    $user = User::factory()->create();

    $this->post('/api/v1/media/images', [
        'kind' => 'cover',
        'file' => TestImages::jpeg(400, 600),
    ], ['Accept' => 'application/json'])->assertUnauthorized();

    $cover = $this->actingAs($user)
        ->post('/api/v1/media/images', [
            'kind' => 'cover',
            'file' => TestImages::jpeg(400, 600),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->json('data');

    expect($cover['kind'])->toBe('cover')
        ->and($cover['width'])->toBeGreaterThan(0)
        ->and($cover['thumb_url'])->not->toBeEmpty()
        ->and($cover['url'])->toContain('/media/')
        ->and($cover)->not->toHaveKey('path');

    $asset = MediaAsset::query()->findOrFail($cover['id']);
    expect(Storage::disk('media')->exists($asset->path))->toBeTrue()
        ->and(Storage::disk('media')->exists((string) $asset->thumb_path))->toBeTrue();

    $chapter = $this->actingAs($user)
        ->post('/api/v1/media/images', [
            'kind' => 'chapter_image',
            'file' => TestImages::png(2000, 800),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->json('data');

    expect($chapter['kind'])->toBe('chapter_image')
        ->and($chapter['width'])->toBeLessThanOrEqual(1600)
        ->and($chapter)->not->toHaveKey('thumb_url');
});

it('rejects unsupported corrupt oversized and animated inputs', function (): void {
    Storage::fake('media');
    config(['media.disk' => 'media']);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/api/v1/media/images', [
            'kind' => 'chapter_image',
            'file' => UploadedFile::fake()->createWithContent('x.gif', 'GIF89a'.str_repeat("\0", 20)),
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable();

    $this->actingAs($user)
        ->post('/api/v1/media/images', [
            'kind' => 'chapter_image',
            'file' => UploadedFile::fake()->createWithContent('bad.jpg', 'not-an-image'),
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable();

    $this->actingAs($user)
        ->post('/api/v1/media/images', [
            'kind' => 'chapter_image',
            'file' => UploadedFile::fake()->create('huge.jpg', 11 * 1024, 'image/jpeg'),
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable();

    $this->actingAs($user)
        ->post('/api/v1/media/images', [
            'kind' => 'chapter_image',
            'file' => TestImages::webp(40, 40, animatedMarker: true),
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable();
});

it('serves private media to owner via bearer and signed url but not to others', function (): void {
    Storage::fake('media');
    config(['media.disk' => 'media']);

    $owner = User::factory()->create();
    $other = User::factory()->create();

    $payload = $this->actingAs($owner)
        ->post('/api/v1/media/images', [
            'kind' => 'chapter_image',
            'file' => TestImages::jpeg(),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->json('data');

    $this->actingAs($owner)
        ->get('/api/v1/media/'.$payload['id'])
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');

    $privateCache = $this->actingAs($owner)
        ->get('/api/v1/media/'.$payload['id'])
        ->headers->get('Cache-Control');
    expect($privateCache)->toContain('private')->toContain('no-store');

    $this->actingAs($other)
        ->getJson('/api/v1/media/'.$payload['id'])
        ->assertNotFound();

    $this->get('/api/v1/public/media/'.$payload['id'])->assertNotFound();

    $this->get($payload['url'])->assertOk();
});
