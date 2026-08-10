<?php

namespace Database\Factories;

use App\Models\BudgetAccounts;
use App\Models\SubBudgetAccounts;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubBudgetAccounts>
 */
class SubBudgetAccountsFactory extends Factory
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
            'code' => $this->faker->word,
            'budget_account_id' => BudgetAccounts::inRandomOrder()->first()->id,
        ];
    }
}
