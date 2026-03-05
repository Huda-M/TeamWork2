<?php
namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamStatistic;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamStatisticFactory extends Factory
{
    protected $model = TeamStatistic::class;

    public function definition(): array
    {
        $totalMembers = $this->faker->numberBetween(3, 10);
        $activeMembers = $this->faker->numberBetween(2, $totalMembers);
        $totalTasks = $this->faker->numberBetween(5, 50);
        $tasksDone = $this->faker->numberBetween(0, $totalTasks);

        return [
            'team_id' => Team::factory(),
            'stat_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),

            'total_members' => $totalMembers,
            'active_members' => $activeMembers,
            'inactive_members' => $totalMembers - $activeMembers,
            'new_members_today' => $this->faker->numberBetween(0, 2),
            'members_left_today' => $this->faker->numberBetween(0, 1),

            'total_tasks' => $totalTasks,
            'tasks_done' => $tasksDone,
            'tasks_todo' => $this->faker->numberBetween(0, $totalTasks - $tasksDone),
            'tasks_in_progress' => $this->faker->numberBetween(0, $totalTasks - $tasksDone),
            'tasks_in_review' => $this->faker->numberBetween(0, $totalTasks - $tasksDone),
            'tasks_created_today' => $this->faker->numberBetween(0, 5),
            'tasks_completed_today' => $this->faker->numberBetween(0, 3),

            'total_estimated_hours' => $this->faker->numberBetween(50, 500),
            'total_actual_hours' => $this->faker->numberBetween(40, 600),
            'total_overtime_hours' => $this->faker->numberBetween(0, 100),

            'completion_rate' => round(($tasksDone / max(1, $totalTasks)) * 100, 2),
            'efficiency_rate' => round($this->faker->randomFloat(2, 60, 95), 2),
            'on_time_rate' => round($this->faker->randomFloat(2, 70, 98), 2),

            'total_score' => $this->faker->numberBetween(100, 5000),
            'average_member_score' => $this->faker->numberBetween(50, 500),
            'team_rank' => $this->faker->optional()->numberBetween(1, 100),

            'lines_of_code' => $this->faker->numberBetween(1000, 50000),
            'commits_count' => $this->faker->numberBetween(5, 100),
            'pull_requests' => $this->faker->numberBetween(2, 50),
            'code_reviews' => $this->faker->numberBetween(3, 60),
            'bugs_fixed' => $this->faker->numberBetween(1, 30),
            'features_delivered' => $this->faker->numberBetween(1, 20),

            'messages_sent' => $this->faker->numberBetween(10, 200),
            'meetings_held' => $this->faker->numberBetween(0, 10),

            'last_activity_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'active_days_streak' => $this->faker->numberBetween(1, 30),
        ];
    }
}
