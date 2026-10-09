<?php

namespace App\Modules\Library\Actions;

use App\Modules\Library\Models\Volume;
use App\Modules\Library\Models\Work;
use App\Modules\Library\Support\PositionRenumerator;
use App\Modules\Library\Support\WorkLocker;
use Illuminate\Support\Facades\DB;

class CreateVolume
{
    public function __construct(
        private readonly WorkLocker $locker,
        private readonly PositionRenumerator $positions,
    ) {}

    public function handle(Work $work, string $title): Volume
    {
        return DB::transaction(function () use ($work, $title): Volume {
            $this->locker->lock($work->id);

            return Volume::query()->create([
                'work_id' => $work->id,
                'title' => $title,
                'position' => $this->positions->nextVolumePosition($work->id),
            ]);
        });
    }
}
