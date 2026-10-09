<?php

namespace App\Modules\Editor\Actions;

use App\Models\User;
use App\Modules\Editor\Models\ChapterDraft;
use App\Modules\Editor\Models\ChapterRevision;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CreateRevision
{
    public function __construct(
        private readonly GetOrCreateDraft $getOrCreateDraft,
    ) {}

    public function handle(string $chapterId, User $user, ?string $label, ?string $idempotencyKey): ChapterRevision
    {
        $idempotencyKey = ($idempotencyKey !== null && $idempotencyKey !== '')
            ? $idempotencyKey
            : null;

        return DB::transaction(function () use ($chapterId, $user, $label, $idempotencyKey): ChapterRevision {
            $this->getOrCreateDraft->handle($chapterId);

            /** @var ChapterDraft $draft */
            $draft = ChapterDraft::query()
                ->whereKey($chapterId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($idempotencyKey !== null) {
                $existing = ChapterRevision::query()
                    ->where('chapter_id', $chapterId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    return $this->resolveIdempotent($existing, $draft, $label);
                }
            }

            try {
                return ChapterRevision::query()->create([
                    'chapter_id' => $chapterId,
                    'content_json' => $draft->content_json,
                    'plain_text' => $draft->plain_text,
                    'source_version' => $draft->version,
                    'created_by' => $user->id,
                    'label' => $label,
                    'idempotency_key' => $idempotencyKey,
                    'created_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                if ($idempotencyKey === null) {
                    throw $exception;
                }

                /** @var ChapterRevision $existing */
                $existing = ChapterRevision::query()
                    ->where('chapter_id', $chapterId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->firstOrFail();

                return $this->resolveIdempotent($existing, $draft, $label);
            }
        });
    }

    private function resolveIdempotent(
        ChapterRevision $existing,
        ChapterDraft $draft,
        ?string $label,
    ): ChapterRevision {
        if ($existing->source_version !== $draft->version || $existing->label !== $label) {
            abort(response()->json([
                'message' => 'Idempotency key reused with a different request payload.',
            ], 409));
        }

        return $existing;
    }
}
