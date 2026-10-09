<?php

namespace App\Modules\Media\Support;

use App\Modules\Media\Models\MediaAsset;
use Illuminate\Support\Facades\URL;

class MediaUrlBuilder
{
    public function privateUrl(MediaAsset $asset, string $variant = MediaAsset::VARIANT_ORIGINAL): string
    {
        return $this->privateSignedUrl($asset->id, $variant);
    }

    public function privateSignedUrl(string $mediaId, string $variant = MediaAsset::VARIANT_ORIGINAL): string
    {
        $ttl = max(60, (int) config('media.private_url_ttl_seconds', 3600));

        return URL::temporarySignedRoute(
            'api.v1.media.show.signed',
            now()->addSeconds($ttl),
            ['media' => $mediaId, 'variant' => $variant],
        );
    }

    public function privateAuthUrl(string $mediaId, string $variant = MediaAsset::VARIANT_ORIGINAL): string
    {
        return route('api.v1.media.show', [
            'media' => $mediaId,
            'variant' => $variant,
        ], absolute: true);
    }

    public function publicUrl(MediaAsset|string $assetOrId, string $variant = MediaAsset::VARIANT_ORIGINAL): string
    {
        $id = $assetOrId instanceof MediaAsset ? $assetOrId->id : $assetOrId;

        return route('api.v1.public.media.show', [
            'media' => $id,
            'variant' => $variant,
        ], absolute: true);
    }

    /**
     * @return array{url: string, width: int, height: int, mime_type: string, byte_size: int}
     */
    public function privateDictionaryEntry(MediaAsset $asset): array
    {
        return [
            'url' => $this->privateUrl($asset),
            'width' => $asset->width,
            'height' => $asset->height,
            'mime_type' => $asset->mime_type,
            'byte_size' => $asset->byte_size,
        ];
    }

    /**
     * @return array{url: string, width: int, height: int, mime_type: string, byte_size: int}
     */
    public function publicDictionaryEntry(MediaAsset $asset): array
    {
        return [
            'url' => $this->publicUrl($asset),
            'width' => $asset->width,
            'height' => $asset->height,
            'mime_type' => $asset->mime_type,
            'byte_size' => $asset->byte_size,
        ];
    }
}
