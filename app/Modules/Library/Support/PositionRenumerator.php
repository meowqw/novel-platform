<?php

namespace App\Modules\Library\Support;

use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Volume;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PositionRenumerator
{
    private const TEMP_OFFSET = 1_000_000;

    /**
     * @param  list<string>  $orderedIds
     */
    public function renumberVolumes(string $workId, array $orderedIds): void
    {
        if ($orderedIds === []) {
            return;
        }

        foreach (array_values($orderedIds) as $index => $id) {
            Volume::query()
                ->where('work_id', $workId)
                ->whereKey($id)
                ->update(['position' => self::TEMP_OFFSET + $index + 1]);
        }

        foreach (array_values($orderedIds) as $index => $id) {
            Volume::query()
                ->where('work_id', $workId)
                ->whereKey($id)
                ->update(['position' => $index + 1]);
        }
    }

    /**
     * @param  list<string>  $orderedIds
     */
    public function renumberChapters(string $workId, ?string $volumeId, array $orderedIds): void
    {
        if ($orderedIds === []) {
            return;
        }

        foreach (array_values($orderedIds) as $index => $id) {
            $this->chapterQuery($workId, $volumeId)
                ->whereKey($id)
                ->update(['position' => self::TEMP_OFFSET + $index + 1]);
        }

        foreach (array_values($orderedIds) as $index => $id) {
            $this->chapterQuery($workId, $volumeId)
                ->whereKey($id)
                ->update(['position' => $index + 1]);
        }
    }

    public function nextVolumePosition(string $workId): int
    {
        return (int) Volume::query()->where('work_id', $workId)->max('position') + 1;
    }

    public function nextChapterPosition(string $workId, ?string $volumeId): int
    {
        return (int) $this->chapterQuery($workId, $volumeId)->max('position') + 1;
    }

    public function compactVolumes(string $workId): void
    {
        /** @var Collection<int, string> $ids */
        $ids = Volume::query()
            ->where('work_id', $workId)
            ->orderBy('position')
            ->pluck('id');

        $this->renumberVolumes($workId, $ids->all());
    }

    public function compactChapters(string $workId, ?string $volumeId): void
    {
        /** @var Collection<int, string> $ids */
        $ids = $this->chapterQuery($workId, $volumeId)
            ->orderBy('position')
            ->pluck('id');

        $this->renumberChapters($workId, $volumeId, $ids->all());
    }

    /**
     * @return Builder<Chapter>
     */
    private function chapterQuery(string $workId, ?string $volumeId)
    {
        $query = Chapter::query()->where('work_id', $workId);

        if ($volumeId === null) {
            $query->whereNull('volume_id');
        } else {
            $query->where('volume_id', $volumeId);
        }

        return $query;
    }
}
