<?php

namespace App\Modules\Editor\Actions;

use App\Modules\Editor\Models\ChapterDraft;
use App\Modules\Editor\Support\PlainTextExtractor;
use Illuminate\Support\Facades\DB;

class GetOrCreateDraft
{
    public function __construct(
        private readonly PlainTextExtractor $plainText,
    ) {}

    public function handle(string $chapterId): ChapterDraft
    {
        $existing = ChapterDraft::query()->find($chapterId);

        if ($existing !== null) {
            return $existing;
        }

        $empty = config('editor.empty_document');
        $plain = $this->plainText->extract($empty);

        DB::table('chapter_drafts')->insertOrIgnore([
            'chapter_id' => $chapterId,
            'content_json' => json_encode($empty, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'plain_text' => $plain,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var ChapterDraft $draft */
        $draft = ChapterDraft::query()->findOrFail($chapterId);

        return $draft;
    }
}
