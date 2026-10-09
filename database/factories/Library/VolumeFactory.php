<?php

namespace Database\Factories\Library;

use App\Modules\Library\Models\Volume;
use App\Modules\Library\Models\Work;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Volume>
 */
class VolumeFactory extends Factory
{
    protected $model = Volume::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_id' => Work::factory(),
            'title' => fake()->sentence(2),
            'position' => 1,
        ];
    }
}
