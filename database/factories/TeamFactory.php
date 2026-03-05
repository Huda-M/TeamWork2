<?php
namespace Database\Factories;

use App\Models\Team;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' Team',
            'description' => $this->faker->paragraph(),
            'formation_type' => $this->faker->randomElement(['random', 'manual', 'mixed']),
            'status' => 'forming',
            'project_id' => Project::factory(),
            'max_members' => $this->faker->numberBetween(3, 10),
            'min_members' => 3,
            'is_public' => $this->faker->boolean(70),
            'join_code' => strtoupper($this->faker->bothify('??##??##')),
            'required_skills' => $this->getRandomSkills(),
            'preferred_skills' => $this->getRandomSkills(2, 4),
            'experience_level' => $this->faker->randomElement(['beginner', 'intermediate', 'advanced', 'expert']),
            'avatar_url' => $this->faker->imageUrl(200, 200, 'teams'),
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }

    public function withMembers(int $count = 3): static
    {
        return $this->afterCreating(function (Team $team) use ($count) {
            $team->teamMembers()->create([
                'programmer_id' => \App\Models\Programmer::factory()->create()->id,
                'role' => 'leader',
                'joined_at' => now(),
            ]);

            for ($i = 1; $i < $count; $i++) {
                $team->teamMembers()->create([
                    'programmer_id' => \App\Models\Programmer::factory()->create()->id,
                    'role' => 'member',
                    'joined_at' => now(),
                ]);
            }
        });
    }

    private function getRandomSkills(int $min = 3, int $max = 6): array
    {
        $skills = [
            'PHP', 'Laravel', 'JavaScript', 'Vue.js', 'React',
            'Node.js', 'Python', 'Django', 'Java', 'Spring',
            'C#', '.NET', 'SQL', 'MongoDB', 'Redis',
            'Docker', 'AWS', 'Git', 'REST API', 'GraphQL'
        ];

        return $this->faker->randomElements($skills, $this->faker->numberBetween($min, $max));
    }
}
