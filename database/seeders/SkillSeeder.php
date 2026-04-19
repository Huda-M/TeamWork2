<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Skill;
use Illuminate\Support\Str;

class SkillSeeder extends Seeder
{
    public function run(): void
    {
        $skills = [
            ['name' => 'JavaScript'],
            ['name' => 'TypeScript'],
            ['name' => 'React'],
            ['name' => 'Vue.js'],
            ['name' => 'Angular'],
            ['name' => 'Next.js'],
            ['name' => 'Nuxt.js'],
            ['name' => 'HTML5'],
            ['name' => 'CSS3'],
            ['name' => 'Tailwind CSS'],
            ['name' => 'Sass/SCSS'],
            ['name' => 'Bootstrap'],

            ['name' => 'PHP'],
            ['name' => 'Laravel'],
            ['name' => 'Python'],
            ['name' => 'Django'],
            ['name' => 'Node.js'],
            ['name' => 'Express.js'],
            ['name' => 'Ruby on Rails'],
            ['name' => 'Java'],
            ['name' => 'Spring Boot'],
            ['name' => 'Go'],
            ['name' => 'Rust'],
            ['name' => 'C#'],
            ['name' => '.NET'],

            ['name' => 'MySQL'],
            ['name' => 'PostgreSQL'],
            ['name' => 'MongoDB'],
            ['name' => 'Redis'],
            ['name' => 'Firebase'],
            ['name' => 'Elasticsearch'],
            ['name' => 'SQLite'],

            ['name' => 'Flutter'],
            ['name' => 'React Native'],
            ['name' => 'Swift'],
            ['name' => 'Kotlin'],
            ['name' => 'Java (Android)'],
            ['name' => 'Ionic'],

            ['name' => 'Docker'],
            ['name' => 'Kubernetes'],
            ['name' => 'AWS'],
            ['name' => 'Azure'],
            ['name' => 'Google Cloud'],
            ['name' => 'Jenkins'],
            ['name' => 'GitHub Actions'],
            ['name' => 'Linux'],
            ['name' => 'Nginx'],

            ['name' => 'Machine Learning'],
            ['name' => 'Deep Learning'],
            ['name' => 'TensorFlow'],
            ['name' => 'PyTorch'],
            ['name' => 'Data Analysis'],
            ['name' => 'Pandas'],
            ['name' => 'NumPy'],
            ['name' => 'LLM'],
            ['name' => 'OpenAI API'],

            ['name' => 'Git'],
            ['name' => 'GitHub'],
            ['name' => 'GitLab'],
            ['name' => 'Jira'],
            ['name' => 'Trello'],
            ['name' => 'Figma'],
            ['name' => 'Adobe XD'],
            ['name' => 'Postman'],
            ['name' => 'Swagger'],
            ['name' => 'VS Code'],
        ];

        foreach ($skills as $skill) {
            Skill::create([
                'name' => $skill['name']
            ]);
        }

        $this->command->info('✅ Skills seeded successfully! (' . count($skills) . ' skills)');
    }
}
