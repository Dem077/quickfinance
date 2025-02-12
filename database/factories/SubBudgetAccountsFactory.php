<?php

namespace Database\Factories;

use App\Models\BudgetAccounts;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SubBudgetAccounts>
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
            'amount' => 500000,
            'budget_account_id' => BudgetAccounts::inRandomOrder()->first()->id,
        ];
    }
}
