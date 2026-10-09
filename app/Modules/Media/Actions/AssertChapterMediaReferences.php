<?php

namespace App\Modules\Media\Actions;

use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Support\MediaIdExtractor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssertChapterMediaReferences
{
    public function __construct(
        private readonly MediaIdExtractor $extractor,
    ) {}

    /**
     * @param  array<string, mixed>  $document
     */
    public function handle(array $document, string $authorId): void
    {
        $ids = $this->extractor->extract($document);

        if ($ids === []) {
            return;
        }

        $maxImages = (int) config('editor.max_images', 100);

        if (count($ids) > $maxImages) {
            throw ValidationException::withMessages([
                'content' => ['Document contains too many images.'],
            ]);
        }

        $found = MediaAsset::query()
            ->whereIn('id', $ids)
            ->where('owner_id', $authorId)
            ->where('kind', MediaAsset::KIND_CHAPTER_IMAGE)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        sort($ids);
        $found = array_values($found);
        sort($found);

        if ($found !== $ids) {
            throw ValidationException::withMessages([
                'content' => ['Document references an invalid image.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public function handleForChapter(array $document, string $chapterId): void
    {
        $authorId = DB::table('chapters')
            ->join('works', 'works.id', '=', 'chapters.work_id')
            ->where('chapters.id', $chapterId)
            ->value('works.author_id');

        if (! is_string($authorId)) {
            throw ValidationException::withMessages([
                'content' => ['Document references an invalid image.'],
            ]);
        }

        $this->handle($document, $authorId);
    }
}
