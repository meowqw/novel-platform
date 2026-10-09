<?php

namespace App\Modules\Publishing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Work;
use App\Modules\Publishing\Http\Resources\PublicWorkResource;
use App\Modules\Publishing\Models\ChapterPublication;
use App\Modules\Publishing\Models\WorkPublication;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class PublicWorkController extends Controller
{
    public function show(Work $work): JsonResponse
    {
        /** @var WorkPublication|null $publication */
        $publication = WorkPublication::query()
            ->whereKey($work->id)
            ->where('is_published', true)
            ->first();

        if ($publication === null) {
            throw (new ModelNotFoundException)->setModel(Work::class, [$work->id]);
        }

        $work->load(['author', 'volumes']);

        $publishedChapterIds = ChapterPublication::query()
            ->where('is_published', true)
            ->whereIn('chapter_id', Chapter::query()->select('id')->where('work_id', $work->id))
            ->pluck('chapter_id');

        $publishedChapters = Chapter::query()
            ->where('work_id', $work->id)
            ->whereIn('id', $publishedChapterIds)
            ->orderBy('position')
            ->get();

        return (new PublicWorkResource($work, $publication, $publishedChapters))
            ->response()
            ->header('Cache-Control', 'no-store');
    }
}
