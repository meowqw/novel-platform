<?php

namespace App\Modules\Library\Actions;

use App\Modules\Library\Models\Work;
use App\Modules\Library\Support\WorkLocker;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class DeleteWork
{
    public function __construct(
        private readonly WorkLocker $locker,
    ) {}

    public function handle(Work $work): void
    {
        try {
            DB::transaction(function () use ($work): void {
                $locked = $this->locker->lock($work->id);
                $locked->delete();
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23503') {
                abort(response()->json([
                    'message' => 'Cannot delete work while editor or publication data exists.',
                ], 409));
            }

            throw $exception;
        }
    }
}
