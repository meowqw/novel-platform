<?php

namespace App\Modules\Publishing\Actions;

use App\Modules\Library\Models\Work;
use App\Modules\Publishing\Models\WorkPublication;
use Illuminate\Support\Facades\DB;

class SetWorkPublication
{
    public function handle(Work $work, bool $published): WorkPublication
    {
        return DB::transaction(function () use ($work, $published): WorkPublication {
            Work::query()->whereKey($work->id)->lockForUpdate()->firstOrFail();

            /** @var WorkPublication|null $row */
            $row = WorkPublication::query()->whereKey($work->id)->lockForUpdate()->first();

            if ($row === null) {
                if (! $published) {
                    return new WorkPublication([
                        'work_id' => $work->id,
                        'is_published' => false,
                    ]);
                }

                $row = new WorkPublication(['work_id' => $work->id]);
            } elseif ($row->is_published === $published) {
                return $row;
            }

            $now = now();

            if ($published) {
                $row->is_published = true;
                $row->published_at = $now;
            } else {
                $row->is_published = false;
                $row->unpublished_at = $now;
            }

            $row->save();

            return $row->refresh();
        });
    }
}
