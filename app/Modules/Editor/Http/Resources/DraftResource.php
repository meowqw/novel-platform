<?php

namespace App\Modules\Editor\Http\Resources;

use App\Modules\Editor\Models\ChapterDraft;
use App\Modules\Editor\Support\PlainTextExtractor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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

        return [
            'chapter_id' => $this->resource->chapter_id,
            'content' => $this->resource->content_json,
            'version' => $this->resource->version,
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
            'stats' => [
                'characters' => $extractor->characterCount($plain),
                'words' => $extractor->wordCount($plain),
            ],
        ];
    }
}
