<?php

namespace App\Modules\Publishing\Support;

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
}
