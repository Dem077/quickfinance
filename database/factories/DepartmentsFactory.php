<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Departments>
 */
class DepartmentsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'hod' => fake()->name(),   
            'petty_cash_float_amount' => fake()->randomFloat(2, 1000, 10000),
            'hod_designation' => fake()->jobTitle(),
        ];
    }
}
