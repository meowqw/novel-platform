<?php

namespace App\Modules\Library\Actions;

use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Volume;
use App\Modules\Library\Models\Work;
use App\Modules\Library\Support\PositionRenumerator;
use App\Modules\Library\Support\WorkLocker;
use Illuminate\Support\Facades\DB;

class DeleteVolume
{
    public function __construct(
        private readonly WorkLocker $locker,
        private readonly PositionRenumerator $positions,
    ) {}

    public function handle(Work $work, Volume $volume): void
    {
        DB::transaction(function () use ($work, $volume): void {
            $this->locker->lock($work->id);

            $chapters = Chapter::query()
                ->where('work_id', $work->id)
                ->where('volume_id', $volume->id)
                ->orderBy('position')
                ->get();

            $next = $this->positions->nextChapterPosition($work->id, null);

            foreach ($chapters as $index => $chapter) {
                $chapter->forceFill([
                    'volume_id' => null,
                    'position' => $next + $index,
                ])->save();
            }

            $volume->delete();

            $this->positions->compactVolumes($work->id);
        });
    }
}
