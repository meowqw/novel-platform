<?php

namespace App\Modules\Library\Actions;

use App\Models\User;
use App\Modules\Library\Models\Work;
use App\Modules\Library\Support\SlugGenerator;
use Illuminate\Support\Facades\DB;

class CreateWork
{
    public function __construct(
        private readonly SlugGenerator $slugs,
    ) {}

    /**
     * @param  array{title: string, description?: string|null, status?: string, slug?: string|null}  $data
     */
    public function handle(User $author, array $data): Work
    {
        return $this->slugs->createWithRetry(function () use ($author, $data): Work {
            return DB::transaction(function () use ($author, $data): Work {
                $slug = $this->slugs->generate(
                    $author->id,
                    $data['title'],
                    $data['slug'] ?? null,
                );

                return Work::query()->create([
                    'author_id' => $author->id,
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'slug' => $slug,
                    'status' => $data['status'] ?? 'ongoing',
                ]);
            });
        });
    }
}
