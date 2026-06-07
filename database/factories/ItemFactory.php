<?php

namespace Database\Factories;

use App\Enums\ItemTypeEnum;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_code' => $this->faker->word,
            'name' => $this->faker->name,
            'type' => ItemTypeEnum::Other,
        ];
    }
}
