<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            SkillSeeder::class,
            TrackSeeder::class,
            ProgrammerSeeder::class,
            ProjectSeeder::class,
            TeamSeeder::class,
            TeamMemberSeeder::class,
            TaskSeeder::class,
            EvaluationSeeder::class,
        ]);
    }
}
