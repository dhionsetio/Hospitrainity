<?php

namespace Database\Factories;

use App\Models\Exercise;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exercise>
 */
class ExerciseFactory extends Factory
{
    protected $model = Exercise::class;

    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'title' => fake()->sentence(2),
            'type' => 'multiple_choice_quiz',
            'content' => [
                'options' => ['Option A', 'Option B'],
                'correct_answer' => 'Option A',
            ],
            'order' => 0,
        ];
    }
}
