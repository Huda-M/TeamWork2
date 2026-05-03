<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Evaluation;
use App\Models\Project;
use App\Models\Team;
use App\Models\Programmer;

class EvaluationSeeder extends Seeder
{
    public function run(): void
    {
        $projects = Project::all();

        foreach ($projects as $project) {
            foreach ($project->teams as $team) {
                $members = $team->activeMembers->pluck('programmer_id')->toArray();
                foreach ($members as $evaluatorId) {
                    foreach ($members as $evaluatedId) {
                        if ($evaluatorId === $evaluatedId) continue;

                        Evaluation::create([
                            'project_id' => $project->id,
                            'team_id' => $team->id,
                            'evaluator_id' => $evaluatorId,
                            'evaluated_id' => $evaluatedId,
                            'technical_skills' => fake()->numberBetween(1, 10),
                            'communication' => fake()->numberBetween(1, 10),
                            'teamwork' => fake()->numberBetween(1, 10),
                            'problem_solving' => fake()->numberBetween(1, 10),
                            'reliability' => fake()->numberBetween(1, 10),
                            'code_quality' => fake()->numberBetween(1, 10),
                            'average_score' => fake()->randomFloat(2, 5, 10),
                            'strengths' => fake()->sentence(),
                            'areas_for_improvement' => fake()->sentence(),
                            'feedback' => fake()->paragraph(),
                            'is_anonymous' => fake()->boolean(),
                            'is_completed' => true,
                            'submitted_at' => now(),
                        ]);
                    }
                }
            }
        }
    }
}
