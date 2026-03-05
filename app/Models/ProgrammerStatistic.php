<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgrammerStatistic extends Model
{
    use HasFactory;

    protected $fillable = [
        'programmer_id',
        'total_tasks_completed',
        'total_hours_worked',
        'average_completion_time',
        'success_rate',
        'total_score_earned',
        'current_streak',
        'longest_streak',
        'last_active_at',
        'weekly_productivity',
        'monthly_productivity',
    ];

    protected $casts = [
        'total_tasks_completed' => 'integer',
        'total_hours_worked' => 'integer',
        'average_completion_time' => 'decimal:2',
        'success_rate' => 'decimal:2',
        'total_score_earned' => 'integer',
        'current_streak' => 'integer',
        'longest_streak' => 'integer',
        'last_active_at' => 'datetime',
        'weekly_productivity' => 'decimal:2',
        'monthly_productivity' => 'decimal:2',
    ];

    public function programmer(): BelongsTo
    {
        return $this->belongsTo(Programmer::class);
    }

    public function updateProductivity(): void
    {
        $lastWeek = now()->subWeek();
        $lastMonth = now()->subMonth();

        $this->weekly_productivity = $this->calculateProductivity($lastWeek);
        $this->monthly_productivity = $this->calculateProductivity($lastMonth);
        $this->save();
    }

    private function calculateProductivity($since): float
    {
        $tasks = $this->programmer->tasks()
            ->where('status', 'done')
            ->where('completed_at', '>=', $since)
            ->get();

        if ($tasks->isEmpty()) {
            return 0;
        }

        $totalEstimated = $tasks->sum('estimated_hours');
        $totalActual = $tasks->sum('actual_hours');

        if ($totalEstimated === 0) {
            return 0;
        }

        return ($totalActual / $totalEstimated) * 100;
    }
}
