<?php

namespace Database\Factories;

use App\Models\Vendors;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendors>
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
