<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Vocabulary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vocabulary>
 */
class VocabularyFactory extends Factory
{
    protected $model = Vocabulary::class;

    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'category' => fake()->word(),
            'order' => 0,
        ];
    }
}
