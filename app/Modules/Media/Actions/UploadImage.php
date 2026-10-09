<?php

namespace App\Modules\Media\Actions;

use App\Models\User;
use App\Modules\Media\Models\MediaAsset;
use App\Modules\Media\Support\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UploadImage
{
    public function __construct(
        private readonly ImageProcessor $processor,
    ) {}

    public function handle(User $owner, UploadedFile $file, string $kind): MediaAsset
    {
        if (! in_array($kind, MediaAsset::KINDS, true)) {
            throw ValidationException::withMessages([
                'kind' => ['Kind must be cover or chapter_image.'],
            ]);
        }

        $processed = $this->processor->process($file, $kind);
        $disk = (string) config('media.disk', 'media');
        $assetId = (string) (new MediaAsset)->newUniqueId();
        $directory = $assetId;
        $originalPath = $directory.'/original.'.$processed['extension'];
        $thumbPath = $processed['thumb'] !== null
            ? $directory.'/thumb.'.$processed['extension']
            : null;

        $written = [];

        try {
            Storage::disk($disk)->put($originalPath, $processed['original']['binary']);
            $written[] = $originalPath;

            if ($thumbPath !== null && $processed['thumb'] !== null) {
                Storage::disk($disk)->put($thumbPath, $processed['thumb']['binary']);
                $written[] = $thumbPath;
            }

            return DB::transaction(function () use (
                $owner,
                $kind,
                $disk,
                $assetId,
                $originalPath,
                $thumbPath,
                $processed,
            ): MediaAsset {
                $asset = new MediaAsset;
                $asset->id = $assetId;
                $asset->owner_id = $owner->id;
                $asset->kind = $kind;
                $asset->disk = $disk;
                $asset->path = $originalPath;
                $asset->mime_type = $processed['mime_type'];
                $asset->byte_size = $processed['original']['byte_size'];
                $asset->width = $processed['original']['width'];
                $asset->height = $processed['original']['height'];
                $asset->checksum_sha256 = $processed['original']['checksum'];

                if ($thumbPath !== null && $processed['thumb'] !== null) {
                    $asset->thumb_path = $thumbPath;
                    $asset->thumb_byte_size = $processed['thumb']['byte_size'];
                    $asset->thumb_width = $processed['thumb']['width'];
                    $asset->thumb_height = $processed['thumb']['height'];
                }

                $asset->save();

                return $asset->refresh();
            });
        } catch (\Throwable $e) {
            foreach ($written as $path) {
                Storage::disk($disk)->delete($path);
            }

            throw $e;
        }
    }
}
