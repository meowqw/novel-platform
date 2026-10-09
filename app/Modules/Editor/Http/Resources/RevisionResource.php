<?php

namespace App\Modules\Editor\Http\Resources;

use App\Modules\Editor\Models\ChapterRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ChapterRevision $resource
 */
class RevisionResource extends JsonResource
{
    public bool $withContent = false;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->resource->id,
            'chapter_id' => $this->resource->chapter_id,
            'source_version' => $this->resource->source_version,
            'label' => $this->resource->label,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];

        if ($this->withContent) {
            $data['content'] = $this->resource->content_json;
            $data['plain_text'] = $this->resource->plain_text;
        }

        return $data;
    }

    public function withContent(bool $withContent = true): static
    {
        $this->withContent = $withContent;

        return $this;
    }
}
