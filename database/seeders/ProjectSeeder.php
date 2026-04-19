<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\Skill;
use App\Models\User;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ البحث عن مستخدم admin
        $admin = User::where('role', 'admin')->first();

        if (!$admin) {
            // إذا لم يوجد admin، قم بإنشائه
            $admin = User::create([
                'full_name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ]);
        }

        $projects = [
            [
                'title' => 'E-Commerce Platform',
                'category_name' => 'E-Commerce',
                'difficulty' => 'intermediate',
                'estimated_duration_days' => 30,
                'max_team_size' => 5,      // ✅ استخدم max_team_size بدلاً من team_size
                'num_of_team' => 3,        // ✅ استخدم num_of_team بدلاً من max_teams
                'description' => 'Build a fully functional e-commerce platform with product management, shopping cart, user authentication, and payment integration.',
                'skills' => ['Laravel', 'React', 'MySQL', 'Tailwind CSS'],
            ],
            [
                'title' => 'Task Management System',
                'category_name' => 'Productivity',
                'difficulty' => 'beginner',
                'estimated_duration_days' => 20,
                'max_team_size' => 4,
                'num_of_team' => 4,
                'description' => 'Create a Trello-like task management system with boards, lists, cards, and real-time updates.',
                'skills' => ['Vue.js', 'Node.js', 'MongoDB', 'Express.js'],
            ],
            [
                'title' => 'Social Media Dashboard',
                'category_name' => 'Analytics',
                'difficulty' => 'advanced',
                'estimated_duration_days' => 45,
                'max_team_size' => 6,
                'num_of_team' => 2,
                'description' => 'Build a social media analytics dashboard that aggregates data from multiple platforms and displays insights.',
                'skills' => ['React', 'Python', 'Django', 'PostgreSQL'],
            ],
            [
                'title' => 'Mobile Fitness App',
                'category_name' => 'Health & Fitness',
                'difficulty' => 'intermediate',
                'estimated_duration_days' => 35,
                'max_team_size' => 5,
                'num_of_team' => 3,
                'description' => 'Develop a mobile app for tracking workouts, nutrition, and health metrics with personalized recommendations.',
                'skills' => ['Flutter', 'Firebase', 'Node.js', 'MongoDB'],
            ],
            [
                'title' => 'AI Chatbot Platform',
                'category_name' => 'Artificial Intelligence',
                'difficulty' => 'advanced',
                'estimated_duration_days' => 60,
                'max_team_size' => 8,
                'num_of_team' => 2,
                'description' => 'Create a customizable chatbot platform using LLMs that can be integrated with various business applications.',
                'skills' => ['Python', 'TensorFlow', 'OpenAI API', 'React'],
            ],
            [
                'title' => 'Real Estate Portal',
                'category_name' => 'Real Estate',
                'difficulty' => 'intermediate',
                'estimated_duration_days' => 40,
                'max_team_size' => 6,
                'num_of_team' => 3,
                'description' => 'Build a real estate listing platform with property search, filters, maps integration, and agent profiles.',
                'skills' => ['Laravel', 'Vue.js', 'MySQL'],
            ],
            [
                'title' => 'Cloud Infrastructure Monitor',
                'category_name' => 'DevOps',
                'difficulty' => 'advanced',
                'estimated_duration_days' => 50,
                'max_team_size' => 7,
                'num_of_team' => 2,
                'description' => 'Create a monitoring system for cloud infrastructure with alerts, metrics visualization, and incident management.',
                'skills' => ['Docker', 'Kubernetes', 'AWS', 'Prometheus'],
            ],
            [
                'title' => 'Online Learning Platform',
                'category_name' => 'Education',
                'difficulty' => 'intermediate',
                'estimated_duration_days' => 45,
                'max_team_size' => 6,
                'num_of_team' => 3,
                'description' => 'Build an e-learning platform with course management, video streaming, quizzes, and progress tracking.',
                'skills' => ['React', 'Node.js', 'MongoDB', 'Socket.io'],
            ],
            [
                'title' => 'Food Delivery App',
                'category_name' => 'Food & Beverage',
                'difficulty' => 'beginner',
                'estimated_duration_days' => 25,
                'max_team_size' => 4,
                'num_of_team' => 4,
                'description' => 'Build a food delivery app with restaurant listings, menu browsing, ordering, and real-time tracking.',
                'skills' => ['Flutter', 'Laravel', 'MySQL', 'Firebase'],
            ],
            [
                'title' => 'Portfolio Builder',
                'category_name' => 'Creative',
                'difficulty' => 'beginner',
                'estimated_duration_days' => 15,
                'max_team_size' => 3,
                'num_of_team' => 5,
                'description' => 'Create a drag-and-drop portfolio builder for creatives to showcase their work.',
                'skills' => ['React', 'Tailwind CSS', 'Node.js'],
            ],
        ];

        foreach ($projects as $projectData) {
            $skills = $projectData['skills'];
            unset($projectData['skills']);

            $project = Project::create(array_merge($projectData, [
                'user_id' => $admin->id,
            ]));

            // ربط المهارات بالمشروع
            foreach ($skills as $skillName) {
                $skill = Skill::where('name', $skillName)->first();
                if ($skill) {
                    $project->skills()->attach($skill->id);
                } else {
                    // إذا كانت المهارة غير موجودة، قم بإنشائها
                    $skill = Skill::create(['name' => $skillName]);
                    $project->skills()->attach($skill->id);
                }
            }
        }

        $this->command->info('✅ Projects seeded successfully! (' . count($projects) . ' projects)');
    }
}
