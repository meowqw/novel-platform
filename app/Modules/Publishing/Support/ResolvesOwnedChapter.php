<?php

namespace App\Modules\Publishing\Support;

use App\Modules\Library\Models\Chapter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

trait ResolvesOwnedChapter
{
    protected function ownedChapter(Request $request, Chapter $chapter): Chapter
    {
        $chapter->loadMissing('work');

        if ($request->user()?->id !== $chapter->work?->author_id) {
            throw (new ModelNotFoundException)->setModel(Chapter::class, [$chapter->id]);
        }

        return $chapter;
    }
}
