<?php

use App\Models\User;
use App\Modules\Editor\Models\ChapterDraft;
use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Work;
use App\Modules\Publishing\Actions\PublishChapter;
use App\Modules\Publishing\Models\ChapterPublication;
use Illuminate\Support\Facades\DB;

it('keeps a consistent snapshot when publishing under draft lock', function (): void {
    $author = User::factory()->create();
    $token = $author->createToken('api')->plainTextToken;
    $work = Work::factory()->create(['author_id' => $author->id]);
    $chapter = Chapter::factory()->create(['work_id' => $work->id, 'position' => 1]);

    $this->withToken($token)->getJson('/api/v1/chapters/'.$chapter->id.'/draft')->assertOk();
    $this->withToken($token)
        ->putJson('/api/v1/chapters/'.$chapter->id.'/draft', [
            'content' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => 'locked']],
                    ],
                ],
            ],
            'version' => 1,
        ])
        ->assertOk();

    DB::transaction(function () use ($chapter): void {
        Chapter::query()->whereKey($chapter->id)->lockForUpdate()->firstOrFail();
        $draft = ChapterDraft::query()->whereKey($chapter->id)->lockForUpdate()->firstOrFail();

        app(PublishChapter::class)->handle($chapter->fresh());

        expect(ChapterPublication::query()->find($chapter->id)?->plain_text)->toBe('locked')
            ->and(ChapterPublication::query()->find($chapter->id)?->source_draft_version)->toBe($draft->version);
    });

    $second = app(PublishChapter::class)->handle($chapter->fresh());
    expect($second->is_published)->toBeTrue()
        ->and($second->plain_text)->toBe('locked');
});
