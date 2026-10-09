<?php

use App\Models\User;
use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Work;
use App\Modules\Publishing\Models\ChapterPublication;
use App\Modules\Publishing\Models\WorkPublication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

function catalogDoc(string $text): array
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

function publishWork(Work $work, ?Carbon $publishedAt = null, bool $isPublished = true): WorkPublication
{
    return WorkPublication::query()->create([
        'work_id' => $work->id,
        'is_published' => $isPublished,
        'published_at' => $isPublished ? ($publishedAt ?? now()) : null,
        'unpublished_at' => $isPublished ? null : now(),
    ]);
}

function publishChapter(Chapter $chapter, ?Carbon $publishedAt = null, bool $isPublished = true): ChapterPublication
{
    return ChapterPublication::query()->create([
        'chapter_id' => $chapter->id,
        'is_published' => $isPublished,
        'content_json' => $isPublished ? catalogDoc('snapshot') : null,
        'plain_text' => $isPublished ? 'snapshot' : null,
        'source_draft_version' => $isPublished ? 1 : null,
        'published_at' => $isPublished ? ($publishedAt ?? now()) : null,
        'unpublished_at' => $isPublished ? null : now(),
    ]);
}

it('lists published works for guests and ignores bearer for visibility', function (): void {
    $author = User::factory()->create(['username' => 'writer_a']);
    $other = User::factory()->create(['username' => 'writer_b']);
    $token = $author->createToken('api')->plainTextToken;

    $published = Work::factory()->create(['author_id' => $author->id, 'title' => 'Visible']);
    $unpublished = Work::factory()->create(['author_id' => $author->id, 'title' => 'Hidden Own']);
    $foreign = Work::factory()->create(['author_id' => $other->id, 'title' => 'Hidden Foreign']);

    publishWork($published, Carbon::parse('2026-10-01T10:00:00Z'));
    publishWork($unpublished, null, false);
    // foreign never published

    $guest = $this->getJson('/api/v1/public/works')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->json();

    expect($guest['data'])->toHaveCount(1)
        ->and($guest['data'][0]['title'])->toBe('Visible')
        ->and($guest['data'][0]['published_chapters_count'])->toBe(0)
        ->and($guest['data'][0]['cover_url'])->toBeNull()
        ->and($guest['data'][0]['author']['username'])->toBe('writer_a')
        ->and($guest['meta']['total'])->toBe(1);

    $encoded = json_encode($guest, JSON_THROW_ON_ERROR);
    expect($encoded)->not->toContain('Hidden Own')
        ->and($encoded)->not->toContain('Hidden Foreign')
        ->and($encoded)->not->toContain($author->email)
        ->and($encoded)->not->toContain('source_draft_version');

    $authed = $this->withToken($token)
        ->getJson('/api/v1/public/works')
        ->assertOk()
        ->json();

    expect($authed['data'])->toHaveCount(1)
        ->and($authed['data'][0]['id'])->toBe($published->id);
});

it('includes empty published works and removes them on unpublish', function (): void {
    $author = User::factory()->create();
    $work = Work::factory()->create(['author_id' => $author->id, 'title' => 'Empty Pub']);
    $pub = publishWork($work, Carbon::parse('2026-10-01T12:00:00Z'));

    $this->getJson('/api/v1/public/works')
        ->assertOk()
        ->assertJsonPath('data.0.published_chapters_count', 0)
        ->assertJsonPath('data.0.last_activity_at', '2026-10-01T12:00:00+00:00')
        ->assertJsonPath('meta.total', 1);

    $pub->update([
        'is_published' => false,
        'unpublished_at' => Carbon::parse('2026-10-02T12:00:00Z'),
    ]);

    $this->getJson('/api/v1/public/works')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.total', 0);

    $pub->update([
        'is_published' => true,
        'published_at' => Carbon::parse('2026-10-03T12:00:00Z'),
        'unpublished_at' => null,
    ]);

    $this->getJson('/api/v1/public/works')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.published_at', '2026-10-03T12:00:00+00:00');
});

it('counts only published chapters and never leaks unpublished work via chapter flags', function (): void {
    $author = User::factory()->create();
    $visible = Work::factory()->create(['author_id' => $author->id, 'title' => 'Counted']);
    $hidden = Work::factory()->create(['author_id' => $author->id, 'title' => 'Secret Work']);

    publishWork($visible, Carbon::parse('2026-10-01T10:00:00Z'));
    // hidden work unpublished, but chapter published
    publishWork($hidden, null, false);

    $c1 = Chapter::factory()->create(['work_id' => $visible->id, 'title' => 'Pub Ch', 'position' => 1]);
    $c2 = Chapter::factory()->create(['work_id' => $visible->id, 'title' => 'Draft Ch', 'position' => 2]);
    $secret = Chapter::factory()->create(['work_id' => $hidden->id, 'title' => 'Leaked Title', 'position' => 1]);

    publishChapter($c1, Carbon::parse('2026-10-02T10:00:00Z'));
    publishChapter($c2, null, false);
    publishChapter($secret, Carbon::parse('2026-10-05T10:00:00Z'));

    $payload = $this->getJson('/api/v1/public/works')->assertOk()->json();

    expect($payload['data'])->toHaveCount(1)
        ->and($payload['data'][0]['published_chapters_count'])->toBe(1)
        ->and($payload['data'][0]['last_activity_at'])->toBe('2026-10-02T10:00:00+00:00');

    $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
    expect($encoded)->not->toContain('Leaked Title')
        ->and($encoded)->not->toContain('Secret Work')
        ->and($encoded)->not->toContain('Draft Ch')
        ->and($encoded)->not->toContain('snapshot');
});

it('searches titles safely across scripts and LIKE metacharacters', function (): void {
    $author = User::factory()->create();

    $cyrillic = Work::factory()->create(['author_id' => $author->id, 'title' => 'Пример Истории']);
    $latin = Work::factory()->create(['author_id' => $author->id, 'title' => 'Alpha Novel']);
    $literal = Work::factory()->create(['author_id' => $author->id, 'title' => '100%_ready\\path']);
    $other = Work::factory()->create(['author_id' => $author->id, 'title' => 'Unrelated']);

    foreach ([$cyrillic, $latin, $literal, $other] as $work) {
        publishWork($work, Carbon::parse('2026-10-01T10:00:00Z'));
    }

    $this->getJson('/api/v1/public/works?q='.urlencode('пример'))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.title', 'Пример Истории');

    $this->getJson('/api/v1/public/works?q=ALPHA')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.title', 'Alpha Novel');

    $this->getJson('/api/v1/public/works?q='.urlencode('100%_ready\\path'))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.title', '100%_ready\\path');

    $this->getJson('/api/v1/public/works?q=%')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->getJson('/api/v1/public/works?q='.urlencode("'; DROP TABLE works;--"))
        ->assertOk()
        ->assertJsonPath('meta.total', 0);

    $this->getJson('/api/v1/public/works?q='.urlencode('   '))
        ->assertOk()
        ->assertJsonPath('meta.total', 4);
});

it('filters by status and validates query params', function (): void {
    $author = User::factory()->create();

    foreach (['ongoing', 'completed', 'hiatus'] as $status) {
        $work = Work::factory()->create([
            'author_id' => $author->id,
            'title' => 'Status '.$status,
            'status' => $status,
        ]);
        publishWork($work, Carbon::parse('2026-10-01T10:00:00Z'));
    }

    $this->getJson('/api/v1/public/works?status=hiatus')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.status', 'hiatus');

    $this->getJson('/api/v1/public/works?status=draft')->assertUnprocessable();
    $this->getJson('/api/v1/public/works?sort=popular')->assertUnprocessable();
    $this->getJson('/api/v1/public/works?page=0')->assertUnprocessable();
    $this->getJson('/api/v1/public/works?per_page=51')->assertUnprocessable();
});

it('sorts by recent published and title with stable tie-breakers', function (): void {
    $author = User::factory()->create();

    $older = Work::factory()->create(['author_id' => $author->id, 'title' => 'Bravo']);
    $newerWork = Work::factory()->create(['author_id' => $author->id, 'title' => 'Alpha']);
    $chapterActive = Work::factory()->create(['author_id' => $author->id, 'title' => 'Charlie']);

    publishWork($older, Carbon::parse('2026-10-01T10:00:00Z'));
    publishWork($newerWork, Carbon::parse('2026-10-03T10:00:00Z'));
    publishWork($chapterActive, Carbon::parse('2026-10-01T12:00:00Z'));

    $chapter = Chapter::factory()->create(['work_id' => $chapterActive->id, 'position' => 1]);
    publishChapter($chapter, Carbon::parse('2026-10-05T10:00:00Z'));

    // private draft-like update must not affect recent
    $older->update(['title' => 'Bravo Edited', 'updated_at' => Carbon::parse('2026-10-10T10:00:00Z')]);

    $recent = $this->getJson('/api/v1/public/works?sort=recent')->assertOk()->json('data');
    expect(collect($recent)->pluck('title')->all())->toBe(['Charlie', 'Alpha', 'Bravo Edited']);

    $published = $this->getJson('/api/v1/public/works?sort=published')->assertOk()->json('data');
    expect(collect($published)->pluck('title')->all())->toBe(['Alpha', 'Charlie', 'Bravo Edited']);

    $title = $this->getJson('/api/v1/public/works?sort=title')->assertOk()->json('data');
    expect(collect($title)->pluck('title')->all())->toBe(['Alpha', 'Bravo Edited', 'Charlie']);
});

it('supports combined filters pagination and preserves query string', function (): void {
    $author = User::factory()->create();

    for ($i = 1; $i <= 5; $i++) {
        $work = Work::factory()->create([
            'author_id' => $author->id,
            'title' => 'Saga Part '.$i,
            'status' => $i % 2 === 0 ? 'completed' : 'ongoing',
        ]);
        publishWork($work, Carbon::parse('2026-10-0'.$i.'T10:00:00Z'));
    }

    $page1 = $this->getJson('/api/v1/public/works?q=Saga&status=ongoing&sort=published&per_page=2&page=1')
        ->assertOk()
        ->json();

    expect($page1['meta']['total'])->toBe(3)
        ->and($page1['meta']['per_page'])->toBe(2)
        ->and($page1['data'])->toHaveCount(2)
        ->and($page1['links']['next'])->toContain('q=Saga')
        ->and($page1['links']['next'])->toContain('status=ongoing')
        ->and($page1['links']['next'])->toContain('sort=published');

    $page2 = $this->getJson('/api/v1/public/works?q=Saga&status=ongoing&sort=published&per_page=2&page=2')
        ->assertOk()
        ->json();

    expect($page2['data'])->toHaveCount(1);

    $empty = $this->getJson('/api/v1/public/works?q=NoSuchTitle&status=ongoing')
        ->assertOk()
        ->json();

    expect($empty['data'])->toBe([])
        ->and($empty['meta']['total'])->toBe(0);
});

it('paginates without duplicates across pages for equal timestamps', function (): void {
    $author = User::factory()->create();
    $stamp = Carbon::parse('2026-10-01T10:00:00Z');
    $ids = [];

    for ($i = 0; $i < 55; $i++) {
        $work = Work::factory()->create([
            'author_id' => $author->id,
            'title' => sprintf('Batch %02d', $i),
        ]);
        publishWork($work, $stamp);
        $ids[] = $work->id;
    }

    $seen = [];
    for ($page = 1; $page <= 3; $page++) {
        $chunk = $this->getJson('/api/v1/public/works?sort=published&per_page=20&page='.$page)
            ->assertOk()
            ->json('data');

        foreach ($chunk as $item) {
            expect($seen)->not->toContain($item['id']);
            $seen[] = $item['id'];
        }
    }

    expect($seen)->toHaveCount(55)
        ->and(collect($seen)->sort()->values()->all())->toEqual(collect($ids)->sort()->values()->all());
});

it('keeps query count bounded without N+1 author loads', function (): void {
    $authors = User::factory()->count(5)->create();

    foreach ($authors as $i => $author) {
        for ($j = 0; $j < 4; $j++) {
            $work = Work::factory()->create([
                'author_id' => $author->id,
                'title' => "Work {$i}-{$j}",
            ]);
            publishWork($work, Carbon::parse('2026-10-01T10:00:00Z')->addMinutes($i * 10 + $j));
        }
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->getJson('/api/v1/public/works?per_page=20')
        ->assertOk()
        ->assertJsonPath('meta.total', 20);

    $queries = collect(DB::getQueryLog());
    $authorSelects = $queries->filter(
        fn (array $q): bool => str_contains(strtolower($q['query']), 'from "users"')
            || str_contains(strtolower($q['query']), 'from users'),
    );

    expect($queries->count())->toBeLessThan(10)
        ->and($authorSelects->count())->toBe(1);
});

it('handles null published_at without crashing last_activity_at', function (): void {
    $author = User::factory()->create();
    $work = Work::factory()->create(['author_id' => $author->id, 'title' => 'Null Stamp']);

    WorkPublication::query()->create([
        'work_id' => $work->id,
        'is_published' => true,
        'published_at' => null,
        'unpublished_at' => null,
    ]);

    $payload = $this->getJson('/api/v1/public/works?sort=recent')
        ->assertOk()
        ->json('data.0');

    expect($payload['title'])->toBe('Null Stamp')
        ->and($payload['published_at'])->toBeNull()
        ->and($payload['last_activity_at'])->toBeNull()
        ->and($payload['published_chapters_count'])->toBe(0);
});
