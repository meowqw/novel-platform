<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Identity\Http\Resources\PublicUserResource;
use App\Modules\Library\Models\Work;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Support\MediaUrlBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property Work $resource
 */
class CatalogWorkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $publishedAt = $this->resource->getAttribute('catalog_published_at');
        $lastActivityAt = $this->resource->getAttribute('last_activity_at');
        /** @var MediaUrlBuilder $urls */
        $urls = app(MediaUrlBuilder::class);
        $coverId = $this->resource->cover_media_id;

        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'slug' => $this->resource->slug,
            'status' => $this->resource->status,
            'author' => new PublicUserResource($this->resource->author),
            // Catalog uses cover thumbnail; public endpoint enforces visibility.
            'cover_url' => $coverId
                ? $urls->publicUrl($coverId, MediaAsset::VARIANT_THUMB)
                : null,
            'published_at' => $this->formatTimestamp($publishedAt),
            'last_activity_at' => $this->formatTimestamp($lastActivityAt),
            'published_chapters_count' => (int) $this->resource->getAttribute('published_chapters_count'),
        ];
    }

    private function formatTimestamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && (str_starts_with($value, '-infinity') || $value === '')) {
            return null;
        }

        return Carbon::parse($value)->toIso8601String();
    }
}
