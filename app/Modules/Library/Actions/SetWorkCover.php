<?php

namespace App\Modules\Library\Actions;

use App\Modules\Library\Models\Work;
use App\Modules\Media\Models\MediaAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetWorkCover
{
    public function handle(Work $work, string $mediaId): Work
    {
        return DB::transaction(function () use ($work, $mediaId): Work {
            /** @var Work $locked */
            $locked = Work::query()->whereKey($work->id)->lockForUpdate()->firstOrFail();

            /** @var MediaAsset|null $asset */
            $asset = MediaAsset::query()->whereKey($mediaId)->lockForUpdate()->first();

            if ($asset === null
                || $asset->owner_id !== $locked->author_id
                || $asset->kind !== MediaAsset::KIND_COVER
            ) {
                throw ValidationException::withMessages([
                    'media_id' => ['Cover media is invalid.'],
                ]);
            }

            $locked->cover_media_id = $asset->id;
            $locked->save();

            return $locked->refresh();
        });
    }

    public function clear(Work $work): Work
    {
        return DB::transaction(function () use ($work): Work {
            /** @var Work $locked */
            $locked = Work::query()->whereKey($work->id)->lockForUpdate()->firstOrFail();
            $locked->cover_media_id = null;
            $locked->save();

            return $locked->refresh();
        });
    }
}
