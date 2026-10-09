<?php

namespace App\Modules\Publishing\Http\Resources;

use App\Modules\Publishing\Models\ChapterPublication;
use App\Modules\Publishing\Models\WorkPublication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property WorkPublication $resource
 */
class WorkPublicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $workId = $this->resource->work_id;

        $publishedChapters = ChapterPublication::query()
            ->where('is_published', true)
            ->whereIn('chapter_id', function ($query) use ($workId): void {
                $query->select('id')
                    ->from('chapters')
                    ->where('work_id', $workId);
            })
            ->count();

        return [
            'work_id' => $workId,
            'is_published' => (bool) $this->resource->is_published,
            'published_at' => $this->resource->published_at?->toIso8601String(),
            'unpublished_at' => $this->resource->unpublished_at?->toIso8601String(),
            'published_chapters_count' => $publishedChapters,
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
