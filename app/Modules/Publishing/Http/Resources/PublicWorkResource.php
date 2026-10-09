<?php

namespace App\Modules\Publishing\Http\Resources;

use App\Modules\Identity\Http\Resources\PublicUserResource;
use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Volume;
use App\Modules\Library\Models\Work;
use App\Modules\Publishing\Models\WorkPublication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @property Work $resource
 */
class PublicWorkResource extends JsonResource
{
    /**
     * @param  Collection<int, Chapter>  $publishedChapters
     */
    public function __construct(
        Work $resource,
        private readonly WorkPublication $publication,
        private readonly Collection $publishedChapters,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $byVolume = $this->publishedChapters->groupBy(
            static fn (Chapter $chapter): string => (string) ($chapter->volume_id ?? ''),
        );

        $volumes = $this->resource->volumes
            ->filter(static function (Volume $volume) use ($byVolume): bool {
                return $byVolume->has($volume->id) && $byVolume->get($volume->id)->isNotEmpty();
            })
            ->values()
            ->map(function (Volume $volume) use ($byVolume): array {
                return [
                    'id' => $volume->id,
                    'title' => $volume->title,
                    'position' => $volume->position,
                    'chapters' => $this->mapChapters($byVolume->get($volume->id)),
                ];
            });

        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'slug' => $this->resource->slug,
            'status' => $this->resource->status,
            'author' => new PublicUserResource($this->resource->author),
            'published_at' => $this->publication->published_at?->toIso8601String(),
            'volumes' => $volumes,
            'chapters' => $this->mapChapters($byVolume->get('', collect())),
        ];
    }

    /**
     * @param  Collection<int, Chapter>|null  $chapters
     * @return list<array<string, mixed>>
     */
    private function mapChapters(?Collection $chapters): array
    {
        return ($chapters ?? collect())
            ->sortBy('position')
            ->values()
            ->map(static fn (Chapter $chapter): array => [
                'id' => $chapter->id,
                'title' => $chapter->title,
                'position' => $chapter->position,
                'volume_id' => $chapter->volume_id,
            ])
            ->all();
    }
}
