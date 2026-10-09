<?php

namespace App\Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Media\Actions\UploadImage;
use App\Modules\Media\Http\Requests\UploadImageRequest;
use App\Modules\Media\Http\Resources\MediaAssetResource;
use Illuminate\Http\JsonResponse;

class MediaUploadController extends Controller
{
    public function store(UploadImageRequest $request, UploadImage $uploadImage): JsonResponse
    {
        $asset = $uploadImage->handle(
            $request->user(),
            $request->file('file'),
            (string) $request->validated('kind'),
        );

        return (new MediaAssetResource($asset))
            ->response()
            ->setStatusCode(201);
    }
}
