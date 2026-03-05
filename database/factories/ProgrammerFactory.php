<?php
namespace Database\Factories;

use App\Models\Programmer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProgrammerFactory extends Factory
{
    protected $model = Programmer::class;

    public function definition(): array
{
    return [
        'user_id' => User::factory()->create(['role' => 'programmer'])->id,
        'specialty' => $this->faker->randomElement(['Backend', 'Frontend', 'Full Stack', 'Mobile', 'AI/ML', 'DevOps']),
        'total_score' => $this->faker->numberBetween(0, 5000),
        'github_username' => $this->faker->userName,
        'is_available' => $this->faker->boolean(80),
        'hourly_rate' => $this->faker->optional(0.7)->numberBetween(20, 200),
        'years_of_experience' => $this->faker->numberBetween(0, 15),
    ];
}

    public function withSkills(array $skills = []): static
    {
        return $this->afterCreating(function (Programmer $programmer) use ($skills) {
            if (empty($skills)) {
                $skills = $this->getRandomSkills();
            }

            foreach ($skills as $skill) {
                $programmer->programmerSkills()->create([
                    'skill_name' => $skill,
                    'level' => $this->faker->randomElement(['beginner', 'intermediate', 'advanced', 'expert']),
                    'years_of_experience' => $this->faker->numberBetween(1, 10),
                    'is_primary' => $this->faker->boolean(30),
                ]);
            }
        });
    }

    private function getRandomSkills(): array
    {
        $allSkills = [
            'PHP', 'Laravel', 'JavaScript', 'Vue.js', 'React', 'Angular',
            'Node.js', 'Python', 'Django', 'Flask', 'Java', 'Spring',
            'C#', '.NET', 'SQL', 'MySQL', 'PostgreSQL', 'MongoDB',
            'Redis', 'Docker', 'Kubernetes', 'AWS', 'Git', 'REST API',
            'GraphQL', 'TypeScript', 'HTML/CSS', 'SASS', 'Bootstrap',
            'Tailwind CSS',
        ];

        return $this->faker->randomElements($allSkills, $this->faker->numberBetween(3, 8));
    }

}
