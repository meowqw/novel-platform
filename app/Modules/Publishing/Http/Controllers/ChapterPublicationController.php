<?php

namespace App\Modules\Publishing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Library\Models\Chapter;
use App\Modules\Publishing\Actions\PublishChapter;
use App\Modules\Publishing\Actions\UnpublishChapter;
use App\Modules\Publishing\Http\Resources\ChapterPublicationResource;
use App\Modules\Publishing\Models\ChapterPublication;
use App\Modules\Publishing\Support\ResolvesOwnedChapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChapterPublicationController extends Controller
{
    use ResolvesOwnedChapter;

    public function show(Request $request, Chapter $chapter): JsonResponse
    {
        $chapter = $this->ownedChapter($request, $chapter);

        $publication = ChapterPublication::query()->find($chapter->id)
            ?? new ChapterPublication([
                'chapter_id' => $chapter->id,
                'is_published' => false,
            ]);

        return (new ChapterPublicationResource($publication))
            ->response()
            ->setStatusCode(200);
    }

    public function publish(
        Request $request,
        Chapter $chapter,
        PublishChapter $publishChapter,
    ): JsonResponse {
        $chapter = $this->ownedChapter($request, $chapter);

        return (new ChapterPublicationResource($publishChapter->handle($chapter)))
            ->response()
            ->setStatusCode(200);
    }

    public function unpublish(
        Request $request,
        Chapter $chapter,
        UnpublishChapter $unpublishChapter,
    ): JsonResponse {
        $chapter = $this->ownedChapter($request, $chapter);

        return (new ChapterPublicationResource($unpublishChapter->handle($chapter)))
            ->response()
            ->setStatusCode(200);
    }
}
