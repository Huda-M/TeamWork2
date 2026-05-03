<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\User;
use App\Models\Skill;
use Illuminate\Support\Facades\DB; // نضيف هذا

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $companies = User::where('role', 'company')->get();
        if ($companies->isEmpty()) {
            $admin = User::where('role', 'admin')->first();
            $companies = collect([$admin]);
        }

        $projectsData = [
            [
                'title' => 'E-Commerce Platform',
                'category_name' => 'E-Commerce',
                'difficulty' => 'intermediate',
                'estimated_duration_days' => 30,
                'team_size' => 5,
                'min_team_size' => 3,
                'max_teams' => 3,
                'description' => 'Build a fully functional e-commerce platform with cart and payments.',
                'skills' => ['Laravel', 'React', 'MySQL'],
            ],
            [
                'title' => 'Task Management System',
                'category_name' => 'Productivity',
                'difficulty' => 'beginner',
                'estimated_duration_days' => 20,
                'team_size' => 4,
                'min_team_size' => 2,
                'max_teams' => 4,
                'description' => 'Create a Trello-like task management system.',
                'skills' => ['Vue.js', 'Node.js', 'MongoDB'],
            ],
            [
                'title' => 'AI Chatbot Platform',
                'category_name' => 'Artificial Intelligence',
                'difficulty' => 'advanced',
                'estimated_duration_days' => 60,
                'team_size' => 6,
                'min_team_size' => 4,
                'max_teams' => 2,
                'description' => 'Create a customizable chatbot platform using LLMs.',
                'skills' => ['Python', 'TensorFlow', 'React'],
            ],
        ];

        foreach ($projectsData as $data) {
            $skills = $data['skills'];
            unset($data['skills']);

            // إضافة الأعمدة القديمة المطلوبة
            $data['max_team_size'] = $data['team_size']; // نفس حجم الفريق
            $data['num_of_team'] = $data['max_teams'];   // عدد الفرق القصوى

            $project = Project::create(array_merge($data, [
                'user_id' => $companies->random()->id,
            ]));

            foreach ($skills as $skillName) {
                $skill = Skill::where('name', $skillName)->first();
                if ($skill) {
                    $project->skills()->attach($skill->id);
                }
            }
        }
    }
}
