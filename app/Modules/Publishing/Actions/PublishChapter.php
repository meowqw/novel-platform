<?php

namespace App\Modules\Publishing\Actions;

use App\Modules\Editor\Models\ChapterDraft;
use App\Modules\Editor\Support\DocumentValidator;
use App\Modules\Editor\Support\PlainTextExtractor;
use App\Modules\Library\Models\Chapter;
use App\Modules\Media\Actions\AssertChapterMediaReferences;
use App\Modules\Media\Actions\SyncChapterPublishedMedia;
use App\Modules\Publishing\Models\ChapterPublication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishChapter
{
    public function __construct(
        private readonly DocumentValidator $validator,
        private readonly PlainTextExtractor $plainText,
        private readonly AssertChapterMediaReferences $assertChapterMedia,
        private readonly SyncChapterPublishedMedia $syncChapterPublishedMedia,
    ) {}

    public function handle(Chapter $chapter): ChapterPublication
    {
        /** @var ChapterDraft|null $draft */
        $draft = ChapterDraft::query()->find($chapter->id);

        if ($draft === null) {
            throw ValidationException::withMessages([
                'draft' => ['Chapter draft is required before publishing.'],
            ]);
        }

        $expectedVersion = $draft->version;
        $document = $this->validator->validate($draft->content_json);
        $this->assertChapterMedia->handleForChapter($document, $chapter->id);
        $plain = $this->plainText->extract($document);

        if (preg_replace('/\s+/u', '', $plain) === '') {
            throw ValidationException::withMessages([
                'draft' => ['Published chapter text cannot be empty or whitespace-only.'],
            ]);
        }

        return DB::transaction(function () use ($chapter, $expectedVersion, $document, $plain): ChapterPublication {
            Chapter::query()->whereKey($chapter->id)->lockForUpdate()->firstOrFail();

            /** @var ChapterDraft|null $locked */
            $locked = ChapterDraft::query()
                ->whereKey($chapter->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw ValidationException::withMessages([
                    'draft' => ['Chapter draft is required before publishing.'],
                ]);
            }

            if ($locked->version !== $expectedVersion) {
                abort(response()->json([
                    'message' => 'Draft changed during publish.',
                    'version' => $locked->version,
                    'updated_at' => $locked->updated_at?->toIso8601String(),
                ], 409));
            }

            /** @var ChapterPublication|null $publication */
            $publication = ChapterPublication::query()
                ->whereKey($chapter->id)
                ->lockForUpdate()
                ->first();

            if ($publication === null) {
                $publication = new ChapterPublication(['chapter_id' => $chapter->id]);
            }

            $publication->fill([
                'is_published' => true,
                'content_json' => $document,
                'plain_text' => $plain,
                'source_draft_version' => $expectedVersion,
                'published_at' => now(),
            ]);
            $publication->save();

            $this->syncChapterPublishedMedia->handle($chapter->work_id, $chapter->id, $document);

            return $publication->refresh();
        });
    }
}
