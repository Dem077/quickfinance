<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vendors>
 */
class VendorsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'address' => $this->faker->address,
            'account_no' => $this->faker->bankAccountNumber,
            'mobile' => $this->faker->phoneNumber,
            'gst_no' => strtoupper($this->faker->bothify('??##########')),
            'bank' => $this->faker->company,
        ];
    }
}
