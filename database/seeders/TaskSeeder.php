<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Task;
use App\Models\Team;
use Faker\Factory as FakerFactory;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $teams = Team::all();

        foreach ($teams as $team) {
            $members = $team->activeMembers->pluck('programmer_id')->toArray();
            if (empty($members)) continue;

            // استخدام Faker بالطريقة الصحيحة
            $faker = FakerFactory::create();

            for ($i = 1; $i <= 5; $i++) {
                Task::create([
                    'team_id' => $team->id,
                    'programmer_id' => $faker->randomElement($members),
                    'title' => $faker->sentence(3),
                    'description' => $faker->paragraph(),
                    'status' => $faker->randomElement(['todo', 'in_progress', 'review', 'done']),
                    'estimated_hours' => $faker->numberBetween(4, 40),
                    'actual_hours' => $faker->optional(0.7)->numberBetween(3, 50),
                    'deadline' => $faker->dateTimeBetween('now', '+30 days'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
