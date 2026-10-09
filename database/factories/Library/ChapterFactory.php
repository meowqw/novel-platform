<?php

namespace Database\Factories\Library;

use App\Modules\Library\Models\Chapter;
use App\Modules\Library\Models\Work;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chapter>
 */
class ChapterFactory extends Factory
{
    protected $model = Chapter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_id' => Work::factory(),
            'volume_id' => null,
            'title' => fake()->sentence(2),
            'position' => 1,
        ];
    }
}
