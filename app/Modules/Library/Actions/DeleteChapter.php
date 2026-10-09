<?php

namespace App\Modules\Library\Actions;

use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Work;
use App\Modules\Library\Support\PositionRenumerator;
use App\Modules\Library\Support\WorkLocker;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class DeleteChapter
{
    public function __construct(
        private readonly WorkLocker $locker,
        private readonly PositionRenumerator $positions,
    ) {}

    public function handle(Work $work, Chapter $chapter): void
    {
        try {
            DB::transaction(function () use ($work, $chapter): void {
                $this->locker->lock($work->id);

                $volumeId = $chapter->volume_id;
                $chapter->delete();

                $this->positions->compactChapters($work->id, $volumeId);
            });
        } catch (QueryException $exception) {
            $this->abortIfRestrictedDataBlocksDelete($exception);

            throw $exception;
        }
    }

    private function abortIfRestrictedDataBlocksDelete(QueryException $exception): void
    {
        if (($exception->errorInfo[0] ?? null) !== '23503') {
            return;
        }

        abort(response()->json([
            'message' => 'Cannot delete chapter while editor or publication data exists.',
        ], 409));
    }
}
