<?php

namespace Database\Factories;

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
        'password' => static::$password ??= Hash::make('password'),
        'remember_token' => Str::random(10),
        'role' => $this->faker->randomElement(['admin', 'company', 'programmer']),
        'user_name' => $this->faker->userName(),
        'country' => $this->faker->optional(0.8)->country(),
        'phone' => $this->faker->optional(0.7)->phoneNumber(),
        'avatar_url' => $this->faker->optional(0.6)->imageUrl(200, 200, 'avatar'),
        'bio' => $this->faker->optional(0.5)->paragraph(),
        'gender' => $this->faker->optional(0.7)->randomElement(['male', 'female']),
        'date_of_birth' => $this->faker->optional(0.6)->dateTimeBetween('-40 years', '-18 years')->format('Y-m-d'),
        'profile_completed' => $this->faker->boolean(70),
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
