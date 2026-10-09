<?php

namespace App\Modules\Editor\Http\Resources;

use App\Modules\Editor\Models\ChapterRevision;
use App\Modules\Media\Support\BuildMediaDictionary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

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
            /** @var array<string, mixed> $content */
            $content = is_array($this->resource->content_json) ? $this->resource->content_json : [];
            $authorId = $this->resolveAuthorId($this->resource->chapter_id);
            /** @var BuildMediaDictionary $dictionary */
            $dictionary = app(BuildMediaDictionary::class);

            $data['content'] = $content;
            $data['plain_text'] = $this->resource->plain_text;
            $data['media'] = $authorId !== null ? $dictionary->forOwner($content, $authorId) : [];
        }

        return $data;
    }

    public function withContent(bool $withContent = true): static
    {
        $this->withContent = $withContent;

        return $this;
    }

    private function resolveAuthorId(string $chapterId): ?string
    {
        $authorId = DB::table('chapters')
            ->join('works', 'works.id', '=', 'chapters.work_id')
            ->where('chapters.id', $chapterId)
            ->value('works.author_id');

        return is_string($authorId) ? $authorId : null;
    }
}
