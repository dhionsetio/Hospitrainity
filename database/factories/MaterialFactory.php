<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    protected $model = Material::class;

    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'type' => 'overview',
            'order' => 0,
        ];
    }
}
