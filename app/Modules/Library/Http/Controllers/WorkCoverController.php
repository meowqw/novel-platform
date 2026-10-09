<?php

namespace App\Modules\Library\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Library\Actions\SetWorkCover;
use App\Modules\Library\Http\Requests\SetWorkCoverRequest;
use App\Modules\Library\Http\Resources\WorkResource;
use App\Modules\Library\Models\Work;
use App\Modules\Library\Support\ResolvesOwnedWork;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WorkCoverController extends Controller
{
    use ResolvesOwnedWork;

    public function update(
        SetWorkCoverRequest $request,
        Work $work,
        SetWorkCover $setWorkCover,
    ): WorkResource {
        $work = $this->ownedWork($request, $work);
        $updated = $setWorkCover->handle($work, (string) $request->validated('media_id'));
        $updated->load('coverMedia');

        return new WorkResource($updated);
    }

    public function destroy(Request $request, Work $work, SetWorkCover $setWorkCover): Response
    {
        $work = $this->ownedWork($request, $work);
        $setWorkCover->clear($work);

        return response()->noContent();
    }
}
