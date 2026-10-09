<?php

namespace App\Modules\Editor\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Editor\Actions\GetOrCreateDraft;
use App\Modules\Editor\Actions\SaveDraft;
use App\Modules\Editor\Http\Requests\SaveDraftRequest;
use App\Modules\Editor\Http\Resources\DraftResource;
use App\Modules\Editor\Support\ResolvesOwnedChapter;
use App\Modules\Library\Models\Chapter;
use Illuminate\Http\Request;

class DraftController extends Controller
{
    use ResolvesOwnedChapter;

    public function show(Request $request, Chapter $chapter, GetOrCreateDraft $getOrCreateDraft): DraftResource
    {
        $chapter = $this->ownedChapter($request, $chapter);
        $draft = $getOrCreateDraft->handle($chapter->id);

        return new DraftResource($draft);
    }

    public function update(SaveDraftRequest $request, Chapter $chapter, SaveDraft $saveDraft): DraftResource
    {
        $chapter = $this->ownedChapter($request, $chapter);

        $draft = $saveDraft->handle(
            $chapter->id,
            $request->validated('content'),
            (int) $request->validated('version'),
        );

        return new DraftResource($draft);
    }
}
