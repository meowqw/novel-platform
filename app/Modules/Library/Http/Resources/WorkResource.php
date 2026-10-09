<?php

namespace App\Modules\Library\Http\Resources;

use App\Modules\Library\Models\Work;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Support\MediaUrlBuilder;
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
        /** @var MediaUrlBuilder $urls */
        $urls = app(MediaUrlBuilder::class);
        $cover = $this->resource->coverMedia;

        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'slug' => $this->resource->slug,
            'status' => $this->resource->status,
            'cover_media_id' => $this->resource->cover_media_id,
            'cover_url' => $cover ? $urls->privateUrl($cover) : null,
            'cover_thumb_url' => $cover?->hasThumb()
                ? $urls->privateUrl($cover, MediaAsset::VARIANT_THUMB)
                : null,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
            'volumes' => VolumeResource::collection($this->whenLoaded('volumes')),
            'chapters' => ChapterResource::collection($this->whenLoaded('chapters')),
        ];
    }
}
