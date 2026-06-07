<?php

namespace Database\Factories;

use App\Models\BudgetAccounts;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetAccounts>
 */
class BudgetAccountsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'expenditure_type' => $this->faker->word,
            'account' => $this->faker->word,
        ];
    }
}
