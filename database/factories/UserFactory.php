<?php

namespace Database\Factories;

use App\Models\Departments;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'avatar_url' => fake()->imageUrl(),
            'bank_account_name' => fake()->name(),
            'bank_account_no' => fake()->bankAccountNumber(),
            'hod_of' => null,
            'is_hod' => false,
            'designation' => fake()->jobTitle(),
            'department_id' => Departments::inRandomOrder()->first()->id,
            'location_id' => Location::inRandomOrder()->first()->id,
            'mobile' => fake()->phoneNumber(),
            'password' => static::$password ??= Hash::make('12345'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
