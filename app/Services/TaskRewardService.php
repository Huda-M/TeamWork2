<?php

namespace App\Services;

use App\Models\Task;
use App\Models\Programmer;
use Carbon\Carbon;

class TaskRewardService
{
    public function calculateRewards(Task $task, Programmer $programmer): array
    {
        return [
            'points' => $this->calculatePoints($task),
            'experience' => $this->calculateExperience($task),
            'badges' => $this->checkForBadges($task, $programmer),
            'level_up' => $this->checkLevelUp($programmer),
        ];
    }

    private function calculatePoints(Task $task): int
    {
        $basePoints = 100;

        $multipliers = [
            'complexity' => [
                'low' => 0.5,
                'medium' => 1.0,
                'high' => 1.5,
                'critical' => 2.0,
            ],
            'timeliness' => $this->getTimelinessMultiplier($task),
            'quality' => $this->getQualityMultiplier($task),
        ];

        $points = $basePoints;

        foreach ($multipliers as $multiplier) {
            $points *= $multiplier;
        }

        return max(50, round($points));
    }

    private function getTimelinessMultiplier(Task $task): float
    {
        if (!$task->deadline || !$task->completed_at) {
            return 1.0;
        }

        $isEarly = $task->completed_at->lt($task->deadline);
        $isOnTime = $task->completed_at->lte($task->deadline);

        if ($isEarly) {
            return 1.2;
        } elseif ($isOnTime) {
            return 1.0;
        } else {
            $hoursLate = $task->deadline->diffInHours($task->completed_at);
            return max(0.5, 1.0 - ($hoursLate * 0.01));
        }
    }

    private function calculateExperience(Task $task): int
    {
        return round($this->calculatePoints($task) * 0.75);
    }

    private function checkForBadges(Task $task, Programmer $programmer): array
    {
        $badges = [];

        if ($task->completed_at->lt($task->deadline)) {
            $badges[] = 'early_completion';
        }

        if ($task->quality_score >= 95) {
            $badges[] = 'perfect_quality';
        }

        if ($this->hasCompletionStreak($programmer)) {
            $badges[] = 'completion_streak';
        }

        return array_unique($badges);
    }

    private function hasCompletionStreak(Programmer $programmer): bool
    {
        $recentCompletions = $programmer->tasks()
            ->where('status', 'done')
            ->where('completed_at', '>=', Carbon::now()->subDays(3))
            ->count();

        return $recentCompletions >= 3;
    }

    private function checkLevelUp(Programmer $programmer): ?array
    {
        $level = $programmer->programmerLevel;

        if (!$level) {
            return null;
        }

        $newXp = $level->current_xp + 100;
        $xpNeeded = $level->xp_to_next_level;

        if ($newXp >= $xpNeeded) {
            $newLevel = $level->current_level + 1;

            return [
                'old_level' => $level->current_level,
                'new_level' => $newLevel,
                'old_xp' => $level->current_xp,
                'new_xp' => $newXp - $xpNeeded,
                'level_name' => $this->getLevelName($newLevel),
            ];
        }

        return null;
    }

    private function getLevelName(int $level): string
    {
        $names = [
            1 => 'Beginner',
            2 => 'Novice',
            3 => 'Apprentice',
            4 => 'Competent',
            5 => 'Proficient',
            6 => 'Expert',
            7 => 'Master',
            8 => 'Grandmaster',
            9 => 'Legend',
            10 => 'Mythic',
        ];

        return $names[$level] ?? "Level $level";
    }
    private function getQualityMultiplier(Task $task): float
{
    if ($task->quality_score === null) {
        return 1.0;
    }

    return match(true) {
        $task->quality_score >= 9 => 1.2,
        $task->quality_score >= 7 => 1.0,
        $task->quality_score >= 5 => 0.8,
        default => 0.5,
    };
}
}
