<?php

namespace App\Modules\Editor\Actions;

use App\Modules\Editor\Models\ChapterDraft;
use App\Modules\Editor\Support\DocumentValidator;
use App\Modules\Editor\Support\PlainTextExtractor;
use App\Modules\Media\Actions\AssertChapterMediaReferences;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveDraft
{
    public function __construct(
        private readonly DocumentValidator $validator,
        private readonly PlainTextExtractor $plainText,
        private readonly GetOrCreateDraft $getOrCreateDraft,
        private readonly AssertChapterMediaReferences $assertChapterMedia,
    ) {}

    /**
     * @param  array<string, mixed>  $content
     */
    public function handle(string $chapterId, array $content, int $expectedVersion): ChapterDraft
    {
        $document = $this->validator->validate($content);
        $this->assertChapterMedia->handleForChapter($document, $chapterId);
        $plain = $this->plainText->extract($document);

        if ($this->plainText->characterCount($plain) > (int) config('editor.max_plain_text_chars')) {
            throw ValidationException::withMessages([
                'content' => ['Plain text exceeds maximum length.'],
            ]);
        }

        return DB::transaction(function () use ($chapterId, $document, $plain, $expectedVersion): ChapterDraft {
            $this->getOrCreateDraft->handle($chapterId);

            $affected = ChapterDraft::query()
                ->where('chapter_id', $chapterId)
                ->where('version', $expectedVersion)
                ->update([
                    'content_json' => $document,
                    'plain_text' => $plain,
                    'version' => $expectedVersion + 1,
                    'updated_at' => now(),
                ]);

            if ($affected !== 1) {
                /** @var ChapterDraft $current */
                $current = ChapterDraft::query()->findOrFail($chapterId);

                abort(response()->json([
                    'message' => 'Draft version conflict.',
                    'version' => $current->version,
                    'updated_at' => $current->updated_at?->toIso8601String(),
                ], 409));
            }

            /** @var ChapterDraft $draft */
            $draft = ChapterDraft::query()->findOrFail($chapterId);

            return $draft;
        });
    }
}
