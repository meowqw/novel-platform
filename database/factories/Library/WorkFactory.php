<?php

namespace Database\Factories\Library;

use App\Models\User;
use App\Modules\Library\Models\Work;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Work>
 */
class WorkFactory extends Factory
{
    protected $model = Work::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'author_id' => User::factory(),
            'title' => $title,
            'description' => fake()->optional()->paragraph(),
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'status' => 'ongoing',
        ];
    }
}
