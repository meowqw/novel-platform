<?php

namespace App\Modules\Editor\Http\Resources;

use App\Modules\Editor\Models\ChapterDraft;
use App\Modules\Editor\Support\PlainTextExtractor;
use App\Modules\Media\Support\BuildMediaDictionary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * @property ChapterDraft $resource
 */
class DraftResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $extractor = app(PlainTextExtractor::class);
        $plain = $this->resource->plain_text;
        /** @var array<string, mixed> $content */
        $content = is_array($this->resource->content_json) ? $this->resource->content_json : [];
        $authorId = $this->resolveAuthorId($this->resource->chapter_id);
        /** @var BuildMediaDictionary $dictionary */
        $dictionary = app(BuildMediaDictionary::class);

        return [
            'chapter_id' => $this->resource->chapter_id,
            'content' => $content,
            'media' => $authorId !== null ? $dictionary->forOwner($content, $authorId) : [],
            'version' => $this->resource->version,
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
            'stats' => [
                'characters' => $extractor->characterCount($plain),
                'words' => $extractor->wordCount($plain),
            ],
        ];
    }

    private function resolveAuthorId(string $chapterId): ?string
    {
        $authorId = DB::table('chapters')
            ->join('works', 'works.id', '=', 'chapters.work_id')
            ->where('chapters.id', $chapterId)
            ->value('works.author_id');

        return is_string($authorId) ? $authorId : null;
    }
}
