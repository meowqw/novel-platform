<?php

namespace App\Modules\Library\Support;

use App\Modules\Library\Models\Work;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class SlugGenerator
{
    private const MAX_ATTEMPTS = 8;

    public function generate(string $authorId, string $title, ?string $explicit = null): string
    {
        $base = $explicit !== null && $explicit !== ''
            ? $this->normalize($explicit)
            : $this->fromTitle($title);

        return $this->allocateUnique($authorId, $base);
    }

    public function allocateUnique(string $authorId, string $base, ?string $ignoreWorkId = null): string
    {
        $candidate = $base;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $query = Work::query()
                ->where('author_id', $authorId)
                ->where('slug', $candidate);

            if ($ignoreWorkId !== null) {
                $query->whereKeyNot($ignoreWorkId);
            }

            if (! $query->exists()) {
                return $candidate;
            }

            $candidate = $base.'-'.($attempt + 1);
        }

        return $base.'-'.Str::lower(Str::random(8));
    }

    public function fromTitle(string $title): string
    {
        $slug = $this->normalize(Str::slug($title));

        if ($slug === '') {
            $slug = 'work-'.Str::lower(Str::substr(str_replace('-', '', (string) Str::uuid()), 0, 8));
        }

        return Str::limit($slug, 220, '');
    }

    public function normalize(string $slug): string
    {
        $slug = Str::lower(trim($slug));
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return Str::limit($slug === '' ? 'work' : $slug, 220, '');
    }

    /**
     * @param  callable(): Work  $create
     */
    public function createWithRetry(callable $create): Work
    {
        $attempt = 0;

        beginning:
        $attempt++;

        try {
            return $create();
        } catch (QueryException $exception) {
            if ($attempt < self::MAX_ATTEMPTS && $this->isUniqueViolation($exception)) {
                goto beginning;
            }

            throw $exception;
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === '23505';
    }
}
