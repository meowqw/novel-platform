<?php

namespace App\Modules\Editor\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Editor\Actions\CreateRevision;
use App\Modules\Editor\Actions\RestoreRevision;
use App\Modules\Editor\Http\Requests\CreateRevisionRequest;
use App\Modules\Editor\Http\Requests\RestoreRevisionRequest;
use App\Modules\Editor\Http\Resources\DraftResource;
use App\Modules\Editor\Http\Resources\RevisionResource;
use App\Modules\Editor\Models\ChapterRevision;
use App\Modules\Editor\Support\ResolvesOwnedChapter;
use App\Modules\Library\Models\Chapter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RevisionController extends Controller
{
    use ResolvesOwnedChapter;

    public function index(Request $request, Chapter $chapter): AnonymousResourceCollection
    {
        $chapter = $this->ownedChapter($request, $chapter);
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $revisions = ChapterRevision::query()
            ->where('chapter_id', $chapter->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return RevisionResource::collection($revisions);
    }

    public function store(
        CreateRevisionRequest $request,
        Chapter $chapter,
        CreateRevision $createRevision,
    ): JsonResponse {
        $chapter = $this->ownedChapter($request, $chapter);

        $revision = $createRevision->handle(
            $chapter->id,
            $request->user(),
            $request->validated('label'),
            $request->header('Idempotency-Key'),
        );

        return (new RevisionResource($revision))
            ->withContent()
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Chapter $chapter, ChapterRevision $revision): RevisionResource
    {
        $chapter = $this->ownedChapter($request, $chapter);
        $this->assertRevisionBelongs($chapter, $revision);

        return (new RevisionResource($revision))->withContent();
    }

    public function restore(
        RestoreRevisionRequest $request,
        Chapter $chapter,
        ChapterRevision $revision,
        RestoreRevision $restoreRevision,
    ): DraftResource {
        $chapter = $this->ownedChapter($request, $chapter);
        $this->assertRevisionBelongs($chapter, $revision);

        $draft = $restoreRevision->handle(
            $revision,
            (int) $request->validated('version'),
        );

        return new DraftResource($draft);
    }

    private function assertRevisionBelongs(Chapter $chapter, ChapterRevision $revision): void
    {
        if ($revision->chapter_id !== $chapter->id) {
            throw (new ModelNotFoundException)->setModel(ChapterRevision::class, [$revision->id]);
        }
    }
}
