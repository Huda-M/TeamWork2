<?php

namespace App\Services;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TaskPerformanceService
{
    public function calculatePerformance(Task $task): array
    {
        try {
            $estimatedHours = $task->estimated_hours ?: 1;
            $actualHours = $task->actual_hours ?: 1;

            return [
                'time_efficiency' => $this->calculateTimeEfficiency($estimatedHours, $actualHours),
                'estimation_accuracy' => $this->calculateEstimationAccuracy($estimatedHours, $actualHours),
                'speed_score' => $this->calculateSpeedScore($task),
                'quality_score' => $this->calculateQualityScore($task),
                'overall_score' => $this->calculateOverallScore($task),
                'performance_level' => $this->getPerformanceLevel($task),
                'is_early' => $this->isCompletedEarly($task),
                'is_on_time' => $this->isCompletedOnTime($task),
                'is_late' => $this->isCompletedLate($task),
            ];
        } catch (\Exception $e) {
            Log::error('Error calculating task performance', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);

            return $this->getDefaultPerformanceData();
        }
    }

    private function getDefaultPerformanceData(): array
    {
        return [
            'time_efficiency' => 100,
            'estimation_accuracy' => 100,
            'speed_score' => 80,
            'quality_score' => 85,
            'overall_score' => 85,
            'performance_level' => 'good',
            'is_early' => false,
            'is_on_time' => true,
            'is_late' => false,
        ];
    }

    public function calculateRewards(array $performance, Task $task): array
    {
        $basePoints = 100;
        $complexityMultiplier = $this->getComplexityMultiplier($task);
        $performanceMultiplier = $performance['overall_score'] / 100;
        $timelinessBonus = $this->getTimelinessBonus($performance);

        $totalPoints = round($basePoints * $complexityMultiplier * $performanceMultiplier + $timelinessBonus);

        return [
            'points' => $totalPoints,
            'experience' => round($totalPoints * 0.5),
            'badges' => $this->getEarnedBadges($totalPoints, $performance),
            'level_up' => $this->checkLevelUp($totalPoints),
            'breakdown' => [
                'base_points' => $basePoints,
                'complexity_multiplier' => $complexityMultiplier,
                'performance_multiplier' => $performanceMultiplier,
                'timeliness_bonus' => $timelinessBonus,
            ],
        ];
    }

    public function checkAchievements(Task $task, array $performance): array
    {
        $achievements = [];

        if ($performance['overall_score'] >= 95) {
            $achievements[] = 'perfect_completion';
        }

        if ($performance['is_early'] && $performance['overall_score'] >= 80) {
            $achievements[] = 'early_excellence';
        }

        if ($task->estimated_hours >= 20 && $performance['time_efficiency'] >= 120) {
            $achievements[] = 'large_task_expert';
        }

        return $achievements;
    }

    private function calculateTimeEfficiency(float $estimated, float $actual): float
    {
        return ($estimated / max($actual, 1)) * 100;
    }

    private function calculateEstimationAccuracy(float $estimated, float $actual): float
    {
        $difference = abs($actual - $estimated);
        return max(0, 100 - ($difference / $estimated * 100));
    }

    private function calculateSpeedScore(Task $task): float
    {
        if (!$task->started_at) {
            return 70;
        }

        $actualDuration = $task->actual_hours ?: now()->diffInHours($task->started_at);
        $estimatedDuration = $task->estimated_hours ?: 1;
        $speedRatio = $estimatedDuration / max($actualDuration, 0.1);

        return match(true) {
            $speedRatio >= 1.5 => 100,
            $speedRatio >= 1.2 => 90,
            $speedRatio >= 0.8 => 80,
            $speedRatio >= 0.5 => 60,
            default => 40,
        };
    }

    private function calculateQualityScore(Task $task): float
    {
        return 85.0;
    }

    private function calculateOverallScore(Task $task): float
    {
        $performance = $this->calculatePerformance($task);

        return (
            min(150, max(0, $performance['time_efficiency'])) * 0.3 +
            min(100, max(0, $performance['estimation_accuracy'])) * 0.2 +
            $performance['speed_score'] * 0.3 +
            $performance['quality_score'] * 0.2
        );
    }

    private function getPerformanceLevel(Task $task): string
    {
        $score = $this->calculateOverallScore($task);

        return match(true) {
            $score >= 90 => 'excellent',
            $score >= 80 => 'very_good',
            $score >= 70 => 'good',
            $score >= 60 => 'average',
            default => 'needs_improvement',
        };
    }

    private function getComplexityMultiplier(Task $task): float
    {
        $estimatedHours = $task->estimated_hours ?: 1;

        return match(true) {
            $estimatedHours >= 40 => 3.0,
            $estimatedHours >= 20 => 2.5,
            $estimatedHours >= 10 => 2.0,
            $estimatedHours >= 5 => 1.5,
            $estimatedHours >= 2 => 1.2,
            default => 1.0,
        };
    }

    private function getTimelinessBonus(array $performance): int
    {
        if ($performance['is_early']) {
            return 50;
        } elseif ($performance['is_on_time']) {
            return 20;
        }

        return 0;
    }

    private function isCompletedEarly(Task $task): bool
    {
        return $task->deadline && now()->lt($task->deadline);
    }

    private function isCompletedOnTime(Task $task): bool
    {
        if (!$task->deadline) return true;

        $deadlineBuffer = clone $task->deadline;
        $deadlineBuffer->addHours(24);

        return now()->lte($deadlineBuffer);
    }

    private function isCompletedLate(Task $task): bool
    {
        return !$this->isCompletedEarly($task) && !$this->isCompletedOnTime($task);
    }

    private function getEarnedBadges(int $points, array $performance): array
    {
        $badges = [];

        if ($points >= 500) {
            $badges[] = 'task_master';
        }

        if ($performance['overall_score'] >= 90) {
            $badges[] = 'perfectionist';
        }

        if ($performance['is_early']) {
            $badges[] = 'early_bird';
        }

        return $badges;
    }

    private function checkLevelUp(int $points): ?array
    {
        return null;
    }
}
