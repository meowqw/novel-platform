<?php

namespace App\Modules\Library\Actions;

use App\Modules\Library\Models\Volume;
use App\Modules\Library\Models\Work;
use App\Modules\Library\Support\PositionRenumerator;
use App\Modules\Library\Support\WorkLocker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorderVolumes
{
    public function __construct(
        private readonly WorkLocker $locker,
        private readonly PositionRenumerator $positions,
    ) {}

    /**
     * @param  list<string>  $orderedIds
     */
    public function handle(Work $work, array $orderedIds): void
    {
        DB::transaction(function () use ($work, $orderedIds): void {
            $this->locker->lock($work->id);

            $existing = Volume::query()
                ->where('work_id', $work->id)
                ->orderBy('position')
                ->pluck('id')
                ->all();

            $this->assertExactSet($existing, $orderedIds);

            if ($orderedIds !== []) {
                $this->positions->renumberVolumes($work->id, $orderedIds);
            }
        });
    }

    /**
     * @param  list<string>  $existing
     * @param  list<string>  $orderedIds
     */
    private function assertExactSet(array $existing, array $orderedIds): void
    {
        if (count($orderedIds) !== count(array_unique($orderedIds))) {
            throw ValidationException::withMessages([
                'order' => ['Order must not contain duplicate IDs.'],
            ]);
        }

        sort($existing);
        $sorted = $orderedIds;
        sort($sorted);

        if ($existing !== $sorted) {
            throw ValidationException::withMessages([
                'order' => ['Order must include every volume of the work exactly once.'],
            ]);
        }
    }
}
