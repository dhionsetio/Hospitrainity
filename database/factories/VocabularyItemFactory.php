<?php

namespace Database\Factories;

use App\Models\Vocabulary;
use App\Models\VocabularyItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VocabularyItem>
 */
class VocabularyItemFactory extends Factory
{
    protected $model = VocabularyItem::class;

    public function definition(): array
    {
        return [
            'vocabulary_id' => Vocabulary::factory(),
            'term' => fake()->word(),
            'details' => fake()->sentence(),
            'media_url' => null,
            'order' => 0,
        ];
    }
}
