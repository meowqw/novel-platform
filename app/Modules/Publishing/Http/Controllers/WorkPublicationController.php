<?php

namespace App\Modules\Publishing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Library\Models\Work;
use App\Modules\Publishing\Actions\SetWorkPublication;
use App\Modules\Publishing\Http\Requests\SetWorkPublicationRequest;
use App\Modules\Publishing\Http\Resources\WorkPublicationResource;
use App\Modules\Publishing\Models\WorkPublication;
use App\Modules\Publishing\Support\ResolvesOwnedWork;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkPublicationController extends Controller
{
    use ResolvesOwnedWork;

    public function show(Request $request, Work $work): JsonResponse
    {
        $work = $this->ownedWork($request, $work);

        $publication = WorkPublication::query()->find($work->id)
            ?? new WorkPublication([
                'work_id' => $work->id,
                'is_published' => false,
            ]);

        return (new WorkPublicationResource($publication))
            ->response()
            ->setStatusCode(200);
    }

    public function update(
        SetWorkPublicationRequest $request,
        Work $work,
        SetWorkPublication $setWorkPublication,
    ): JsonResponse {
        $work = $this->ownedWork($request, $work);

        $publication = $setWorkPublication->handle(
            $work,
            $request->boolean('published'),
        );

        return (new WorkPublicationResource($publication))
            ->response()
            ->setStatusCode(200);
    }
}
