<?php

namespace Database\Factories;

use App\Models\Programmer;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'programmer_id' => Programmer::inRandomOrder()->first()?->id ?? 1,
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['todo', 'in_progress', 'review', 'done']),
            'estimated_hours' => $this->faker->numberBetween(4, 40),
            'actual_hours' => $this->faker->optional(0.7)->numberBetween(3, 50),
            'deadline' => $this->faker->dateTimeBetween('now', '+30 days'),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high']),
            'complexity' => $this->faker->randomElement(['easy', 'medium', 'hard']),
            'progress_percentage' => $this->faker->numberBetween(0, 100),
            'needs_review' => $this->faker->boolean(30),
            'is_blocked' => false,
        ];
    }
}
