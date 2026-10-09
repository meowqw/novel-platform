<?php

namespace App\Modules\Publishing\Http\Resources;

use App\Modules\Editor\Support\PlainTextExtractor;
use App\Modules\Library\Models\Chapter;
use App\Modules\Publishing\Models\ChapterPublication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Chapter $resource
 */
class PublicChapterResource extends JsonResource
{
    public function __construct(
        Chapter $resource,
        private readonly ChapterPublication $publication,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $extractor = app(PlainTextExtractor::class);
        $plain = (string) $this->publication->plain_text;

        return [
            'id' => $this->resource->id,
            'work_id' => $this->resource->work_id,
            'volume_id' => $this->resource->volume_id,
            'title' => $this->resource->title,
            'position' => $this->resource->position,
            'content' => $this->publication->content_json,
            'published_at' => $this->publication->published_at?->toIso8601String(),
            'stats' => [
                'characters' => $extractor->characterCount($plain),
                'words' => $extractor->wordCount($plain),
            ],
        ];
    }
}
