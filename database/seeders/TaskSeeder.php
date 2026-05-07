<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Task;
use App\Models\Team;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $teams = Team::all();

        foreach ($teams as $team) {
            $members = $team->activeMembers->pluck('programmer_id')->toArray();
            if (empty($members)) continue;

            for ($i = 1; $i <= 5; $i++) {
                Task::create([
                    'team_id' => $team->id,
                    'programmer_id' => collect($members)->random(),
                    'title' => fake()->sentence(3),
                    'description' => fake()->paragraph(),
                    'status' => collect(['todo', 'in_progress', 'review', 'done'])->random(),
                    'estimated_hours' => fake()->numberBetween(4, 40),
                    'actual_hours' => fake()->optional(0.7)->numberBetween(3, 50),
                    'deadline' => fake()->dateTimeBetween('now', '+30 days'),
                ]);
            }
        }
    }
}
