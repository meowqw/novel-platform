<?php

namespace App\Modules\Library\Actions;

use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Volume;
use App\Modules\Library\Models\Work;
use App\Modules\Library\Support\PositionRenumerator;
use App\Modules\Library\Support\WorkLocker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorderChapters
{
    public function __construct(
        private readonly WorkLocker $locker,
        private readonly PositionRenumerator $positions,
    ) {}

    /**
     * @param  list<string>  $orderedIds
     */
    public function handle(Work $work, ?string $volumeId, array $orderedIds): void
    {
        DB::transaction(function () use ($work, $volumeId, $orderedIds): void {
            $this->locker->lock($work->id);

            if ($volumeId !== null) {
                $volume = Volume::query()->whereKey($volumeId)->first();

                if ($volume === null || $volume->work_id !== $work->id) {
                    throw ValidationException::withMessages([
                        'volume_id' => [__('validation.exists', ['attribute' => 'volume_id'])],
                    ]);
                }
            }

            $query = Chapter::query()->where('work_id', $work->id);

            if ($volumeId === null) {
                $query->whereNull('volume_id');
            } else {
                $query->where('volume_id', $volumeId);
            }

            $existing = $query->orderBy('position')->pluck('id')->all();

            if (count($orderedIds) !== count(array_unique($orderedIds))) {
                throw ValidationException::withMessages([
                    'order' => ['Order must not contain duplicate IDs.'],
                ]);
            }

            $sortedExisting = $existing;
            $sortedOrdered = $orderedIds;
            sort($sortedExisting);
            sort($sortedOrdered);

            if ($sortedExisting !== $sortedOrdered) {
                throw ValidationException::withMessages([
                    'order' => ['Order must include every chapter in the target list exactly once.'],
                ]);
            }

            $this->positions->renumberChapters($work->id, $volumeId, $orderedIds);
        });
    }
}
