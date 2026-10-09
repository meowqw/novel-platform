<?php

namespace App\Modules\Library\Actions;

use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Volume;
use App\Modules\Library\Models\Work;
use App\Modules\Library\Support\PositionRenumerator;
use App\Modules\Library\Support\WorkLocker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateChapter
{
    public function __construct(
        private readonly WorkLocker $locker,
        private readonly PositionRenumerator $positions,
    ) {}

    public function handle(Work $work, string $title, ?string $volumeId): Chapter
    {
        return DB::transaction(function () use ($work, $title, $volumeId): Chapter {
            $this->locker->lock($work->id);

            if ($volumeId !== null) {
                $volume = Volume::query()->whereKey($volumeId)->first();

                if ($volume === null || $volume->work_id !== $work->id) {
                    throw ValidationException::withMessages([
                        'volume_id' => [__('validation.exists', ['attribute' => 'volume_id'])],
                    ]);
                }
            }

            return Chapter::query()->create([
                'work_id' => $work->id,
                'volume_id' => $volumeId,
                'title' => $title,
                'position' => $this->positions->nextChapterPosition($work->id, $volumeId),
            ]);
        });
    }
}
