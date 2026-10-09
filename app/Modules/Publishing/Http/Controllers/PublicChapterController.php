<?php

namespace App\Modules\Publishing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Library\Models\Chapter;
use App\Modules\Publishing\Http\Resources\PublicChapterResource;
use App\Modules\Publishing\Models\ChapterPublication;
use App\Modules\Publishing\Models\WorkPublication;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class PublicChapterController extends Controller
{
    public function show(Chapter $chapter): JsonResponse
    {
        $chapter->loadMissing('work');

        $workPublished = WorkPublication::query()
            ->whereKey($chapter->work_id)
            ->where('is_published', true)
            ->exists();

        /** @var ChapterPublication|null $publication */
        $publication = ChapterPublication::query()
            ->whereKey($chapter->id)
            ->where('is_published', true)
            ->first();

        if (! $workPublished || $publication === null) {
            throw (new ModelNotFoundException)->setModel(Chapter::class, [$chapter->id]);
        }

        return (new PublicChapterResource($chapter, $publication))
            ->response()
            ->header('Cache-Control', 'no-store');
    }
}
