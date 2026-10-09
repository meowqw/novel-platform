<?php

namespace App\Modules\Library\Actions;

use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Volume;
use App\Modules\Library\Models\Work;
use App\Modules\Library\Support\PositionRenumerator;
use App\Modules\Library\Support\WorkLocker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoveChapter
{
    public function __construct(
        private readonly WorkLocker $locker,
        private readonly PositionRenumerator $positions,
    ) {}

    public function handle(Work $work, Chapter $chapter, ?string $targetVolumeId, int $targetPosition): Chapter
    {
        return DB::transaction(function () use ($work, $chapter, $targetVolumeId, $targetPosition): Chapter {
            $this->locker->lock($work->id);

            if ($targetVolumeId !== null) {
                $volume = Volume::query()->whereKey($targetVolumeId)->first();

                if ($volume === null || $volume->work_id !== $work->id) {
                    throw ValidationException::withMessages([
                        'volume_id' => [__('validation.exists', ['attribute' => 'volume_id'])],
                    ]);
                }
            }

            $sourceVolumeId = $chapter->volume_id;

            $destinationIds = $this->idsInGroup($work->id, $targetVolumeId);
            $destinationIds = array_values(array_filter(
                $destinationIds,
                fn (string $id): bool => $id !== $chapter->id,
            ));

            $targetPosition = max(1, min($targetPosition, count($destinationIds) + 1));
            array_splice($destinationIds, $targetPosition - 1, 0, [$chapter->id]);

            // Park the moving chapter with a temporary high position and clear volume
            // so source compact / destination renumber do not collide.
            $chapter->forceFill([
                'volume_id' => null,
                'position' => 2_000_000,
            ])->save();

            if ($sourceVolumeId !== $targetVolumeId) {
                $this->positions->compactChapters($work->id, $sourceVolumeId);
            }

            // Assign destination membership before renumber into final positions.
            Chapter::query()->whereKey($chapter->id)->update([
                'volume_id' => $targetVolumeId,
                'position' => 2_000_001,
            ]);

            $this->positions->renumberChapters($work->id, $targetVolumeId, $destinationIds);

            return $chapter->refresh();
        });
    }

    /**
     * @return list<string>
     */
    private function idsInGroup(string $workId, ?string $volumeId): array
    {
        $query = Chapter::query()->where('work_id', $workId)->orderBy('position');

        if ($volumeId === null) {
            $query->whereNull('volume_id');
        } else {
            $query->where('volume_id', $volumeId);
        }

        return $query->pluck('id')->all();
    }
}
