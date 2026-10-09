<?php

namespace App\Modules\Library\Http\Resources;

use App\Modules\Library\Models\Chapter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Chapter $resource
 */
class ChapterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'work_id' => $this->resource->work_id,
            'volume_id' => $this->resource->volume_id,
            'title' => $this->resource->title,
            'position' => $this->resource->position,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
