<?php

namespace App\Modules\Library\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Library\Actions\CreateVolume;
use App\Modules\Library\Actions\DeleteVolume;
use App\Modules\Library\Actions\ReorderVolumes;
use App\Modules\Library\Http\Requests\ReorderRequest;
use App\Modules\Library\Http\Requests\StoreVolumeRequest;
use App\Modules\Library\Http\Requests\UpdateVolumeRequest;
use App\Modules\Library\Http\Resources\VolumeResource;
use App\Modules\Library\Models\Volume;
use App\Modules\Library\Models\Work;
use App\Modules\Library\Support\ResolvesOwnedWork;
use App\Modules\Library\Support\WorkLocker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class VolumeController extends Controller
{
    use ResolvesOwnedWork;

    public function store(StoreVolumeRequest $request, Work $work, CreateVolume $createVolume): JsonResponse
    {
        $work = $this->ownedWork($request, $work);
        $volume = $createVolume->handle($work, $request->validated('title'));

        return (new VolumeResource($volume))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateVolumeRequest $request,
        Work $work,
        Volume $volume,
        WorkLocker $locker,
    ): VolumeResource {
        $work = $this->ownedWork($request, $work);
        $volume = $this->ownedVolume($work, $volume);

        $updated = DB::transaction(function () use ($work, $volume, $request, $locker): Volume {
            $locker->lock($work->id);
            $volume->title = $request->validated('title');
            $volume->save();

            return $volume->refresh();
        });

        return new VolumeResource($updated);
    }

    public function destroy(
        Request $request,
        Work $work,
        Volume $volume,
        DeleteVolume $deleteVolume,
    ): Response {
        $work = $this->ownedWork($request, $work);
        $volume = $this->ownedVolume($work, $volume);
        $deleteVolume->handle($work, $volume);

        return response()->noContent();
    }

    public function reorder(ReorderRequest $request, Work $work, ReorderVolumes $reorderVolumes): Response
    {
        $work = $this->ownedWork($request, $work);
        $reorderVolumes->handle($work, $request->validated('order'));

        return response()->noContent();
    }
}
