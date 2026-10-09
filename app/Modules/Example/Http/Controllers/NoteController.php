<?php

namespace App\Modules\Example\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Example\Actions\CreateNote;
use App\Modules\Example\Http\Requests\StoreNoteRequest;
use App\Modules\Example\Http\Resources\NoteResource;
use App\Modules\Example\Models\Note;
use Illuminate\Http\JsonResponse;

class NoteController extends Controller
{
    public function store(StoreNoteRequest $request, CreateNote $createNote): JsonResponse
    {
        $note = $createNote->handle($request->validated('title'));

        return (new NoteResource($note))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Note $note): NoteResource
    {
        return new NoteResource($note);
    }
}
