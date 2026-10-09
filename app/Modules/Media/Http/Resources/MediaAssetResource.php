<?php

namespace App\Modules\Media\Http\Resources;

use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Support\MediaUrlBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property MediaAsset $resource
 */
class MediaAssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MediaUrlBuilder $urls */
        $urls = app(MediaUrlBuilder::class);

        $data = [
            'id' => $this->resource->id,
            'kind' => $this->resource->kind,
            'url' => $urls->privateUrl($this->resource),
            'width' => $this->resource->width,
            'height' => $this->resource->height,
            'mime_type' => $this->resource->mime_type,
            'byte_size' => $this->resource->byte_size,
        ];

        if ($this->resource->hasThumb()) {
            $data['thumb_url'] = $urls->privateUrl($this->resource, MediaAsset::VARIANT_THUMB);
            $data['thumb_width'] = $this->resource->thumb_width;
            $data['thumb_height'] = $this->resource->thumb_height;
        }

        return $data;
    }
}
