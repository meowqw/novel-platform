<?php

namespace App\Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Media\Actions\IsPubliclyReachableMedia;
use App\Modules\Media\Actions\ServeMediaFile;
use App\Modules\Media\Models\MediaAsset;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaDeliveryController extends Controller
{
    public function showPrivate(
        Request $request,
        MediaAsset $media,
        ServeMediaFile $serveMediaFile,
    ): StreamedResponse {
        if ($request->user()?->id !== $media->owner_id) {
            throw (new ModelNotFoundException)->setModel(MediaAsset::class, [$media->id]);
        }

        $variant = (string) $request->query('variant', MediaAsset::VARIANT_ORIGINAL);

        return $serveMediaFile->handle($media, $variant, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function showSigned(
        Request $request,
        MediaAsset $media,
        ServeMediaFile $serveMediaFile,
    ): StreamedResponse {
        // Signature validated by middleware; still require ownership binding via signed params only.
        // Signed URLs are issued only to the owner after auth checks in MediaUrlBuilder callers.
        $variant = (string) $request->query('variant', MediaAsset::VARIANT_ORIGINAL);

        return $serveMediaFile->handle($media, $variant, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function showPublic(
        Request $request,
        MediaAsset $media,
        IsPubliclyReachableMedia $isPubliclyReachableMedia,
        ServeMediaFile $serveMediaFile,
    ): StreamedResponse {
        if (! $isPubliclyReachableMedia->handle($media)) {
            throw (new ModelNotFoundException)->setModel(MediaAsset::class, [$media->id]);
        }

        $variant = (string) $request->query('variant', MediaAsset::VARIANT_ORIGINAL);
        $ttl = max(0, (int) config('media.public_cache_seconds', 60));

        return $serveMediaFile->handle($media, $variant, [
            'Cache-Control' => "public, max-age={$ttl}, must-revalidate",
        ]);
    }
}
