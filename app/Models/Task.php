<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasStatistics;

class Task extends Model
{
    use HasFactory, HasStatistics;

    protected $fillable = [
        'team_id',
        'programmer_id',
        'project_id',
        'title',
        'description',
        'status',
        'estimated_hours',
        'actual_hours',
        'deadline',
        'priority',
        'complexity',
        'progress_percentage',
        'required_skills',
        'assigned_skills',
        'started_at',
        'completed_at',
        'reviewed_at',
        'reviewed_by',
        'completion_notes',
        'quality_score',
        'quality_feedback',
        'code_analysis_id',
        'needs_review',
        'is_blocked',
        'block_reason',
        'assigned_by',
        'assigned_at',
        'reassigned_from',
        'reassigned_at',
        'git_link',
    'tags',
'created_by',
    ];

    protected $casts = [
        'estimated_hours' => 'integer',
        'actual_hours' => 'integer',
        'deadline' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'assigned_at' => 'datetime',
        'reassigned_at' => 'datetime',
        'priority' => 'integer',
        'progress_percentage' => 'integer',
        'quality_score' => 'integer',
        'required_skills' => 'array',
        'assigned_skills' => 'array',
        'needs_review' => 'boolean',
        'is_blocked' => 'boolean',
        'tags' => 'array',
    ];

    /**
     * Get the team that owns the task
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
{
    return $this->belongsTo(Programmer::class, 'created_by');
}

public function attachments(): HasMany
{
    return $this->hasMany(TaskAttachment::class);
}

    /**
     * Get the programmer assigned to the task
     */
    public function programmer(): BelongsTo
    {
        return $this->belongsTo(Programmer::class);
    }

    /**
     * Get the project that owns the task
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the programmer who assigned this task
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(Programmer::class, 'assigned_by');
    }

    /**
     * Get the programmer who reviewed this task
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Programmer::class, 'reviewed_by');
    }

    /**
     * Get the code analysis for this task
     */
    public function codeAnalysis(): BelongsTo
    {
        return $this->belongsTo(CodeAnalysis::class);
    }

    /**
     * Get the programmer this task was reassigned from
     */
    public function reassignedFrom(): BelongsTo
    {
        return $this->belongsTo(Programmer::class, 'reassigned_from');
    }

    /**
     * Scope a query to only include tasks with a specific status
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include tasks assigned to a specific programmer
     */
    public function scopeAssignedTo($query, $programmerId)
    {
        return $query->where('programmer_id', $programmerId);
    }

    /**
     * Scope a query to only include overdue tasks
     */
    public function scopeOverdue($query)
    {
        return $query->where('deadline', '<', now())
                     ->whereNotIn('status', ['done', 'cancelled']);
    }

    /**
     * Scope a query to only include tasks with upcoming deadlines
     */
    public function scopeUpcoming($query, $days = 7)
    {
        return $query->where('deadline', '>', now())
                     ->where('deadline', '<=', now()->addDays($days))
                     ->whereNotIn('status', ['done', 'cancelled']);
    }

    /**
     * Check if task is overdue
     */
    public function isOverdue(): bool
    {
        return $this->deadline->isPast() &&
               !in_array($this->status, ['done', 'cancelled']);
    }

    /**
     * Check if task is blocked
     */
    public function isBlocked(): bool
    {
        return $this->is_blocked;
    }

    /**
     * Check if task needs review
     */
    public function needsReview(): bool
    {
        return $this->needs_review || $this->status === 'review';
    }

    /**
     * Mark task as completed
     */
    public function markAsCompleted(?int $actualHours = null): void
    {
        $updates = [
            'status' => 'done',
            'completed_at' => now(),
            'progress_percentage' => 100,
        ];

        if ($actualHours) {
            $updates['actual_hours'] = $actualHours;
        }

        $this->update($updates);

        // Award points to programmer
        if ($this->programmer) {
            $this->programmer->addScore(50, 'Task completed', [
                'task_id' => $this->id,
                'title' => $this->title,
            ]);
        }

        // Update team statistics
        $this->team?->updateStatistics();
    }

    /**
     * Calculate task completion percentage based on status
     */
    public function calculateProgressPercentage(): int
    {
        switch ($this->status) {
            case 'todo':
                return 0;
            case 'in_progress':
                return $this->progress_percentage ?: 50;
            case 'review':
                return 90;
            case 'done':
                return 100;
            case 'cancelled':
                return 0;
            default:
                return 0;
        }
    }
}
