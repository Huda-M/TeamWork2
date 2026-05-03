<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Team;
use App\Models\Project;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $projects = Project::all();

        foreach ($projects as $project) {
            // أنشئ 2-3 فرق لكل مشروع
            $numTeams = rand(2, 3);
            for ($i = 1; $i <= $numTeams; $i++) {
                Team::create([
                    'name' => "Team {$i} for {$project->title}",
                    'description' => 'This is a test team',
                    'formation_type' => 'manual',
                    'status' => 'active',
                    'project_id' => $project->id,
                    'is_public' => true,
                    'experience_level' => 'intermediate',
                ]);
            }
        }
    }
}
