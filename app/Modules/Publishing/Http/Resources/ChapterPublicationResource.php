<?php

namespace App\Modules\Publishing\Http\Resources;

use App\Modules\Publishing\Models\ChapterPublication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ChapterPublication $resource
 */
class ChapterPublicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'chapter_id' => $this->resource->chapter_id,
            'is_published' => (bool) $this->resource->is_published,
            'source_draft_version' => $this->resource->source_draft_version,
            'published_at' => $this->resource->published_at?->toIso8601String(),
            'unpublished_at' => $this->resource->unpublished_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
            'has_snapshot' => $this->resource->content_json !== null,
        ];
    }
}
