<?php

namespace App\Traits;

trait BroadcastsWithData
{
    protected function formatProgrammerData($programmer): array
    {
        return [
            'id' => $programmer->id,
            'name' => $programmer->user->name,
            'username' => $programmer->user->user_name,
            'avatar_url' => $programmer->user->avatar_url,
            'specialty' => $programmer->specialty,
            'total_score' => $programmer->total_score,
        ];
    }

    protected function formatTeamData($team): array
    {
        return [
            'id' => $team->id,
            'name' => $team->name,
            'description' => $team->description,
            'current_members' => $team->getMembersCount(),
            'max_members' => $team->max_members,
            'is_public' => $team->is_public,
        ];
    }

    protected function formatTaskData($task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status,
            'deadline' => $task->deadline?->toISOString(),
            'priority' => $this->calculateTaskPriority($task),
        ];
    }

    protected function calculateTaskPriority($task): string
    {
        if (!$task->deadline) return 'medium';

        $daysUntilDeadline = now()->diffInDays($task->deadline, false);

        return match(true) {
            $daysUntilDeadline < 0 => 'overdue',
            $daysUntilDeadline <= 1 => 'high',
            $daysUntilDeadline <= 3 => 'medium',
            default => 'low',
        };
    }

    protected function shouldBroadcast(): bool
    {
        return config('broadcasting.default') !== 'null' &&
               app()->environment(['local', 'staging', 'production']);
    }
}
