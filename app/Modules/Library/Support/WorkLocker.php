<?php

namespace App\Modules\Library\Support;

use App\Modules\Library\Models\Work;

class WorkLocker
{
    public function lock(string $workId): Work
    {
        /** @var Work $work */
        $work = Work::query()->whereKey($workId)->lockForUpdate()->firstOrFail();

        return $work;
    }
}
