<?php

namespace App\Modules\Library\Actions;

use App\Modules\Library\Models\Work;
use App\Modules\Library\Support\SlugGenerator;
use App\Modules\Library\Support\WorkLocker;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class UpdateWork
{
    private const MAX_ATTEMPTS = 8;

    public function __construct(
        private readonly WorkLocker $locker,
        private readonly SlugGenerator $slugs,
    ) {}

    /**
     * @param  array{title?: string, description?: string|null, status?: string, slug?: string}  $data
     */
    public function handle(Work $work, array $data): Work
    {
        $attempt = 0;

        beginning:
        $attempt++;

        try {
            return DB::transaction(function () use ($work, $data): Work {
                $locked = $this->locker->lock($work->id);

                if (array_key_exists('title', $data)) {
                    $locked->title = $data['title'];
                }

                if (array_key_exists('description', $data)) {
                    $locked->description = $data['description'];
                }

                if (array_key_exists('status', $data)) {
                    $locked->status = $data['status'];
                }

                if (array_key_exists('slug', $data)) {
                    $locked->slug = $this->slugs->allocateUnique(
                        $locked->author_id,
                        $this->slugs->normalize($data['slug']),
                        $locked->id,
                    );
                }

                $locked->save();

                return $locked->refresh();
            });
        } catch (QueryException $exception) {
            if ($attempt < self::MAX_ATTEMPTS && ($exception->errorInfo[0] ?? null) === '23505') {
                goto beginning;
            }

            throw $exception;
        }
    }
}
