<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\MaterialItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialItem>
 */
class MaterialItemFactory extends Factory
{
    protected $model = MaterialItem::class;

    public function definition(): array
    {
        return [
            'material_id' => Material::factory(),
            'title' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'url' => null,
            'audio_url' => null,
            'order' => 0,
        ];
    }
}
