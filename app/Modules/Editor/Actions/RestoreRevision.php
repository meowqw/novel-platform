<?php

namespace App\Modules\Editor\Actions;

use App\Modules\Editor\Models\ChapterDraft;
use App\Modules\Editor\Models\ChapterRevision;
use Illuminate\Support\Facades\DB;

class RestoreRevision
{
    public function handle(ChapterRevision $revision, int $expectedVersion): ChapterDraft
    {
        return DB::transaction(function () use ($revision, $expectedVersion): ChapterDraft {
            $affected = ChapterDraft::query()
                ->where('chapter_id', $revision->chapter_id)
                ->where('version', $expectedVersion)
                ->update([
                    'content_json' => $revision->content_json,
                    'plain_text' => $revision->plain_text,
                    'version' => $expectedVersion + 1,
                    'updated_at' => now(),
                ]);

            if ($affected !== 1) {
                /** @var ChapterDraft|null $current */
                $current = ChapterDraft::query()->find($revision->chapter_id);

                abort(response()->json([
                    'message' => 'Draft version conflict.',
                    'version' => $current?->version,
                    'updated_at' => $current?->updated_at?->toIso8601String(),
                ], 409));
            }

            /** @var ChapterDraft $draft */
            $draft = ChapterDraft::query()->findOrFail($revision->chapter_id);

            return $draft;
        });
    }
}
