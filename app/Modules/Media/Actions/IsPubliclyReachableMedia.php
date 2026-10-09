<?php

namespace App\Modules\Media\Actions;

use App\Modules\Library\Models\Work;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Models\PublishedMediaReference;
use Illuminate\Support\Facades\DB;

class IsPubliclyReachableMedia
{
    public function handle(MediaAsset $asset): bool
    {
        if ($this->isLivePublishedCover($asset->id)) {
            return true;
        }

        return $this->isPublishedChapterReference($asset->id);
    }

    private function isLivePublishedCover(string $mediaId): bool
    {
        return Work::query()
            ->where('cover_media_id', $mediaId)
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('work_publications')
                    ->whereColumn('work_publications.work_id', 'works.id')
                    ->where('work_publications.is_published', true);
            })
            ->exists();
    }

    private function isPublishedChapterReference(string $mediaId): bool
    {
        return PublishedMediaReference::query()
            ->where('media_id', $mediaId)
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('work_publications')
                    ->whereColumn('work_publications.work_id', 'published_media_references.work_id')
                    ->where('work_publications.is_published', true);
            })
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('chapter_publications')
                    ->whereColumn('chapter_publications.chapter_id', 'published_media_references.chapter_id')
                    ->where('chapter_publications.is_published', true);
            })
            ->exists();
    }
}
