<?php

namespace App\Modules\Library\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Library\Actions\CreateWork;
use App\Modules\Library\Actions\DeleteWork;
use App\Modules\Library\Actions\UpdateWork;
use App\Modules\Library\Http\Requests\StoreWorkRequest;
use App\Modules\Library\Http\Requests\UpdateWorkRequest;
use App\Modules\Library\Http\Resources\WorkResource;
use App\Modules\Library\Models\Work;
use App\Modules\Library\Support\ResolvesOwnedWork;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class WorkController extends Controller
{
    use ResolvesOwnedWork;

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $works = Work::query()
            ->with('coverMedia')
            ->where('author_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return WorkResource::collection($works);
    }

    public function store(StoreWorkRequest $request, CreateWork $createWork): JsonResponse
    {
        $work = $createWork->handle(
            $request->user(),
            $request->safe()->only(['title', 'description', 'status', 'slug']),
        );

        return (new WorkResource($work))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Work $work): WorkResource
    {
        $work = $this->ownedWork($request, $work);
        $work->load(['volumes', 'chapters', 'coverMedia']);

        return new WorkResource($work);
    }

    public function update(UpdateWorkRequest $request, Work $work, UpdateWork $updateWork): WorkResource
    {
        $work = $this->ownedWork($request, $work);

        $updated = $updateWork->handle(
            $work,
            $request->safe()->only(['title', 'description', 'status', 'slug']),
        );

        return new WorkResource($updated);
    }

    public function destroy(Request $request, Work $work, DeleteWork $deleteWork): Response
    {
        $work = $this->ownedWork($request, $work);
        $deleteWork->handle($work);

        return response()->noContent();
    }
}
