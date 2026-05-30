<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Task;
use App\Models\Team;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $teams = Team::with('activeMembers')->get();

        foreach ($teams as $team) {
            $members = $team->activeMembers->pluck('programmer_id')->toArray();
            if (empty($members)) continue;

            for ($i = 1; $i <= 5; $i++) {
                Task::create([
                    'team_id' => $team->id,
                    'programmer_id' => $this->faker->randomElement($members),
                    'title' => $this->faker->sentence(3),
                    'description' => $this->faker->paragraph(),
                    'status' => $this->faker->randomElement(['todo', 'in_progress', 'review', 'done']),
                    'estimated_hours' => $this->faker->numberBetween(4, 40),
                    'actual_hours' => $this->faker->optional(0.7)->numberBetween(3, 50),
                    'deadline' => $this->faker->dateTimeBetween('now', '+30 days'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
    }
}
