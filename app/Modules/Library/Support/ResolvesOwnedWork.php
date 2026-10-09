<?php

namespace App\Modules\Library\Support;

use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Volume;
use App\Modules\Library\Models\Work;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

trait ResolvesOwnedWork
{
    protected function ownedWork(Request $request, Work $work): Work
    {
        if ($request->user()?->id !== $work->author_id) {
            throw (new ModelNotFoundException)->setModel(Work::class, [$work->id]);
        }

        return $work;
    }

    protected function ownedVolume(Work $work, Volume $volume): Volume
    {
        if ($volume->work_id !== $work->id) {
            throw (new ModelNotFoundException)->setModel(Volume::class, [$volume->id]);
        }

        return $volume;
    }

    protected function ownedChapter(Work $work, Chapter $chapter): Chapter
    {
        if ($chapter->work_id !== $work->id) {
            throw (new ModelNotFoundException)->setModel(Chapter::class, [$chapter->id]);
        }

        return $chapter;
    }
}
