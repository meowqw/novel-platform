<?php

namespace App\Modules\Media\Actions;

use App\Modules\Media\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ServeMediaFile
{
    /**
     * @param  array<string, string>  $cacheHeaders
     */
    public function handle(MediaAsset $asset, string $variant, array $cacheHeaders): StreamedResponse
    {
        $variant = $variant === MediaAsset::VARIANT_THUMB
            ? MediaAsset::VARIANT_THUMB
            : MediaAsset::VARIANT_ORIGINAL;

        if ($variant === MediaAsset::VARIANT_THUMB) {
            if (! $asset->hasThumb()) {
                throw new NotFoundHttpException;
            }

            $path = (string) $asset->thumb_path;
        } else {
            $path = $asset->path;
        }

        $disk = Storage::disk($asset->disk);

        if (! $disk->exists($path)) {
            throw new NotFoundHttpException;
        }

        $stream = $disk->readStream($path);

        if ($stream === false) {
            throw new NotFoundHttpException;
        }

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, array_merge([
            'Content-Type' => $asset->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ], $cacheHeaders));
    }
}
