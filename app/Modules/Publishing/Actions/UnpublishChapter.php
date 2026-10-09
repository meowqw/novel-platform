<?php

namespace App\Modules\Publishing\Actions;

use App\Modules\Library\Models\Chapter;
use App\Modules\Media\Actions\SyncChapterPublishedMedia;
use App\Modules\Publishing\Models\ChapterPublication;
use Illuminate\Support\Facades\DB;

class UnpublishChapter
{
    public function __construct(
        private readonly SyncChapterPublishedMedia $syncChapterPublishedMedia,
    ) {}

    public function handle(Chapter $chapter): ChapterPublication
    {
        return DB::transaction(function () use ($chapter): ChapterPublication {
            Chapter::query()->whereKey($chapter->id)->lockForUpdate()->firstOrFail();

            /** @var ChapterPublication|null $publication */
            $publication = ChapterPublication::query()
                ->whereKey($chapter->id)
                ->lockForUpdate()
                ->first();

            if ($publication === null) {
                $this->syncChapterPublishedMedia->handle($chapter->work_id, $chapter->id, null);

                return new ChapterPublication([
                    'chapter_id' => $chapter->id,
                    'is_published' => false,
                ]);
            }

            if (! $publication->is_published) {
                $this->syncChapterPublishedMedia->handle($chapter->work_id, $chapter->id, null);

                return $publication;
            }

            $publication->is_published = false;
            $publication->unpublished_at = now();
            $publication->save();

            $this->syncChapterPublishedMedia->handle($chapter->work_id, $chapter->id, null);

            return $publication->refresh();
        });
    }
}
