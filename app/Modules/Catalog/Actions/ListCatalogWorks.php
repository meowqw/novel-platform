<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Library\Models\Work;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListCatalogWorks
{
    /**
     * @param  array{q?: string|null, status?: string|null, sort: string, per_page: int}  $filters
     * @return LengthAwarePaginator<int, Work>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $chapterAgg = <<<'SQL'
            SELECT c.work_id,
                   COUNT(*)::int AS published_chapters_count,
                   MAX(cp.published_at) AS max_chapter_published_at
            FROM chapters c
            INNER JOIN chapter_publications cp ON cp.chapter_id = c.id
            WHERE cp.is_published = true
            GROUP BY c.work_id
            SQL;

        $query = Work::query()
            ->select([
                'works.id',
                'works.author_id',
                'works.title',
                'works.description',
                'works.slug',
                'works.status',
                'works.cover_media_id',
                'works.created_at',
                'works.updated_at',
                'wp.published_at as catalog_published_at',
            ])
            ->selectRaw('COALESCE(chapter_agg.published_chapters_count, 0) AS published_chapters_count')
            ->selectRaw(
                "NULLIF(
                    GREATEST(
                        COALESCE(wp.published_at, '-infinity'::timestamptz),
                        COALESCE(chapter_agg.max_chapter_published_at, '-infinity'::timestamptz)
                    ),
                    '-infinity'::timestamptz
                ) AS last_activity_at"
            )
            ->join('work_publications as wp', function ($join): void {
                $join->on('wp.work_id', '=', 'works.id')
                    ->where('wp.is_published', '=', true);
            })
            ->leftJoin(DB::raw("({$chapterAgg}) as chapter_agg"), 'chapter_agg.work_id', '=', 'works.id')
            ->with('author');

        if (! empty($filters['status'])) {
            $query->where('works.status', $filters['status']);
        }

        if (! empty($filters['q'])) {
            $escaped = str_replace(
                ['\\', '%', '_'],
                ['\\\\', '\\%', '\\_'],
                $filters['q'],
            );

            $query->whereRaw(
                "lower(works.title) LIKE lower(?) ESCAPE '\\'",
                ['%'.$escaped.'%'],
            );
        }

        $this->applySort($query, $filters['sort']);

        return $query
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    /**
     * @param  Builder<Work>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'published' => $query
                ->orderByRaw("COALESCE(wp.published_at, '-infinity'::timestamptz) DESC")
                ->orderBy('works.id'),
            'title' => $query
                ->orderByRaw('lower(works.title) ASC')
                ->orderBy('works.id'),
            default => $query
                ->orderByRaw(
                    "NULLIF(
                        GREATEST(
                            COALESCE(wp.published_at, '-infinity'::timestamptz),
                            COALESCE(chapter_agg.max_chapter_published_at, '-infinity'::timestamptz)
                        ),
                        '-infinity'::timestamptz
                    ) DESC NULLS LAST"
                )
                ->orderBy('works.id'),
        };
    }
}
