<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\User;
use App\Models\Skill;
use Illuminate\Support\Facades\DB;

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
                'description' => 'Build a fully functional e-commerce platform with cart and payments.',
                'estimated_duration_days' => 30,
                'status' => 'pending',
                'github_url' => 'https://github.com/example/ecommerce',
                'skills' => ['Laravel', 'React', 'MySQL'],
            ],
            [
                'title' => 'Task Management System',
                'category_name' => 'Productivity',
                'description' => 'Create a Trello-like task management system.',
                'estimated_duration_days' => 20,
                'status' => 'pending',
                'github_url' => 'https://github.com/example/taskmanager',
                'skills' => ['Vue.js', 'Node.js', 'MongoDB'],
            ],
            [
                'title' => 'AI Chatbot Platform',
                'category_name' => 'Artificial Intelligence',
                'description' => 'Create a customizable chatbot platform using LLMs.',
                'estimated_duration_days' => 60,
                'status' => 'pending',
                'github_url' => 'https://github.com/example/chatbot',
                'skills' => ['Python', 'TensorFlow', 'React'],
            ],
        ];

        foreach ($projectsData as $data) {
            $skills = $data['skills'];
            unset($data['skills']);

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
