<?php

namespace App\Modules\Media\Actions;

use App\Modules\Media\Models\PublishedMediaReference;
use App\Modules\Media\Support\MediaIdExtractor;
use Illuminate\Support\Str;

class SyncChapterPublishedMedia
{
    public function __construct(
        private readonly MediaIdExtractor $extractor,
    ) {}

    /**
     * Replace published media references for a chapter from the given snapshot JSON.
     * Must run inside the publish/unpublish transaction.
     *
     * @param  array<string, mixed>|null  $document  null clears all refs (unpublish)
     */
    public function handle(string $workId, string $chapterId, ?array $document): void
    {
        PublishedMediaReference::query()
            ->where('chapter_id', $chapterId)
            ->delete();

        if ($document === null) {
            return;
        }

        $ids = $this->extractor->extract($document);

        foreach ($ids as $mediaId) {
            PublishedMediaReference::query()->create([
                'id' => (string) Str::uuid(),
                'media_id' => $mediaId,
                'work_id' => $workId,
                'chapter_id' => $chapterId,
            ]);
        }
    }
}
