<?php

namespace App\Modules\Library\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Library\Actions\CreateChapter;
use App\Modules\Library\Actions\DeleteChapter;
use App\Modules\Library\Actions\MoveChapter;
use App\Modules\Library\Actions\ReorderChapters;
use App\Modules\Library\Http\Requests\MoveChapterRequest;
use App\Modules\Library\Http\Requests\ReorderChaptersRequest;
use App\Modules\Library\Http\Requests\StoreChapterRequest;
use App\Modules\Library\Http\Requests\UpdateChapterRequest;
use App\Modules\Library\Http\Resources\ChapterResource;
use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Work;
use App\Modules\Library\Support\ResolvesOwnedWork;
use App\Modules\Library\Support\WorkLocker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ChapterController extends Controller
{
    use ResolvesOwnedWork;

    public function store(StoreChapterRequest $request, Work $work, CreateChapter $createChapter): JsonResponse
    {
        $work = $this->ownedWork($request, $work);

        $chapter = $createChapter->handle(
            $work,
            $request->validated('title'),
            $request->safe()->input('volume_id'),
        );

        return (new ChapterResource($chapter))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateChapterRequest $request,
        Work $work,
        Chapter $chapter,
        WorkLocker $locker,
    ): ChapterResource {
        $work = $this->ownedWork($request, $work);
        $chapter = $this->ownedChapter($work, $chapter);

        $updated = DB::transaction(function () use ($work, $chapter, $request, $locker): Chapter {
            $locker->lock($work->id);
            $chapter->title = $request->validated('title');
            $chapter->save();

            return $chapter->refresh();
        });

        return new ChapterResource($updated);
    }

    public function destroy(
        Request $request,
        Work $work,
        Chapter $chapter,
        DeleteChapter $deleteChapter,
    ): Response {
        $work = $this->ownedWork($request, $work);
        $chapter = $this->ownedChapter($work, $chapter);
        $deleteChapter->handle($work, $chapter);

        return response()->noContent();
    }

    public function reorder(ReorderChaptersRequest $request, Work $work, ReorderChapters $reorderChapters): Response
    {
        $work = $this->ownedWork($request, $work);

        $reorderChapters->handle(
            $work,
            $request->validated('volume_id'),
            $request->validated('order'),
        );

        return response()->noContent();
    }

    public function move(
        MoveChapterRequest $request,
        Work $work,
        Chapter $chapter,
        MoveChapter $moveChapter,
    ): ChapterResource {
        $work = $this->ownedWork($request, $work);
        $chapter = $this->ownedChapter($work, $chapter);

        $moved = $moveChapter->handle(
            $work,
            $chapter,
            $request->validated('volume_id'),
            (int) $request->validated('position'),
        );

        return new ChapterResource($moved);
    }
}
