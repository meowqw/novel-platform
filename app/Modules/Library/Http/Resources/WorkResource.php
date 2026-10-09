<?php

namespace App\Modules\Library\Http\Resources;

use App\Modules\Library\Models\Work;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Work $resource
 */
class WorkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'slug' => $this->resource->slug,
            'status' => $this->resource->status,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
            'volumes' => VolumeResource::collection($this->whenLoaded('volumes')),
            'chapters' => ChapterResource::collection($this->whenLoaded('chapters')),
        ];
    }
}
