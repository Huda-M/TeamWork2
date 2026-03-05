<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Programmer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'specialty',
        'total_score',
        'github_username',
        'behance_url',
        'current_team_id',
        'is_available',
        'hourly_rate',
        'preferred_working_hours',
        'timezone',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'hourly_rate' => 'decimal:2',
        'preferred_working_hours' => 'array',
    ];


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teamMessages(): HasMany
    {
        return $this->hasMany(TeamMessage::class);
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function splitTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'split_by');
    }

    public function programmerSkills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'programmer_skills')
            ->withTimestamps();
    }

    public function programmerLevel(): HasOne
    {
        return $this->hasOne(ProgrammerLevel::class);
    }

    public function programmerActivities(): HasMany
    {
        return $this->hasMany(ProgrammerActivity::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot('role', 'joined_at', 'left_at')
            ->withTimestamps();
    }

    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function ledTeams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->wherePivot('role', 'leader')
            ->whereNull('team_members.left_at');
    }

    public function teamJoinRequests(): HasMany
    {
        return $this->hasMany(TeamJoinRequest::class);
    }

    public function programmerBadges(): HasMany
    {
        return $this->hasMany(ProgrammerBadge::class);
    }

    public function scoreLogs(): HasMany
    {
        return $this->hasMany(ProgrammerScoreLog::class);
    }

    public function statistics(): HasOne
    {
        return $this->hasOne(ProgrammerStatistic::class);
    }

    public function evaluationsMade(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'evaluator_id');
    }

    public function evaluationsReceived(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'evaluated_id');
    }

    public function codeAnalyses(): HasMany
    {
        return $this->hasMany(CodeAnalyses::class);
    }


    public function projectInvitations(): HasMany
    {
        return $this->hasMany(ProjectInvitation::class);
    }

    public function sentProjectInvitations(): HasMany
    {
        return $this->hasMany(ProjectInvitation::class, 'invited_by');
    }

    public function receivedProjectInvitations(): HasMany
    {
        return $this->hasMany(ProjectInvitation::class, 'programmer_id');
    }

    public function teamInvitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class, 'programmer_id');
    }

    public function sentInvitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class, 'invited_by');
    }


    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function comAndProEvaluations(): HasMany
    {
        return $this->hasMany(ComAndProEvaluation::class);
    }


    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeBySkill($query, $skillId)
    {
        return $query->whereHas('programmerSkills', function ($q) use ($skillId) {
            $q->where('skills.id', $skillId);
        });
    }

    public function scopeWithHighScore($query, $minScore = 500)
    {
        return $query->where('total_score', '>=', $minScore);
    }


    public function getActiveTeamAttribute(): ?Team
    {
        return $this->teams()
            ->wherePivotNull('left_at')
            ->where('teams.status', 'active')
            ->first();
    }

    public function getIsInTeamAttribute(): bool
    {
        return !is_null($this->active_team);
    }

    public function getExperienceLevelAttribute(): string
    {
        if ($this->total_score >= 2000) return 'expert';
        if ($this->total_score >= 1000) return 'advanced';
        if ($this->total_score >= 500) return 'intermediate';
        return 'beginner';
    }


    public function canJoinTeam(Team $team): bool
    {
        return !$this->is_in_team &&
            $team->hasVacancy() &&
            !$this->hasPendingInvitation($team->id) &&
            $this->meetsTeamRequirements($team);
    }

    public function hasPendingInvitation($teamId): bool
    {
        return TeamInvitation::where('team_id', $teamId)
            ->where('programmer_id', $this->id)
            ->where('status', 'pending')
            ->exists();
    }

    public function meetsTeamRequirements(Team $team): bool
    {
        if ($team->required_skills) {
            $requiredSkills = json_decode($team->required_skills, true);
            $programmerSkills = $this->programmerSkills()->pluck('skills.name')->toArray();

            $matchingSkills = array_intersect($programmerSkills, $requiredSkills);
            if (count($matchingSkills) < count($requiredSkills)) {
                return false;
            }
        }

        $levelOrder = ['beginner' => 1, 'intermediate' => 2, 'advanced' => 3, 'expert' => 4];
        $programmerLevel = $levelOrder[$this->experience_level] ?? 0;
        $teamLevel = $levelOrder[$team->experience_level] ?? 0;

        return $programmerLevel >= $teamLevel;
    }


    public function addScore(int $points, string $reason, array $metadata = []): void
    {
        $this->increment('total_score', $points);

        ProgrammerScoreLog::create([
            'programmer_id' => $this->id,
            'points' => $points,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }

    public function updateStatistics(): void
    {
        $stats = $this->statistics()->firstOrCreate([]);

        $stats->update([
            'total_tasks_completed' => $this->tasks()->where('status', 'done')->count(),
            'total_hours_worked' => $this->tasks()->where('status', 'done')->sum('actual_hours'),
            'average_completion_time' => $this->calculateAverageCompletionTime(),
            'success_rate' => $this->calculateSuccessRate(),
            'last_active_at' => now(),
        ]);
    }

    private function calculateAverageCompletionTime(): float
    {
        $completedTasks = $this->tasks()
            ->where('status', 'done')
            ->whereNotNull('actual_hours')
            ->get();

        return $completedTasks->isEmpty() ? 0 : $completedTasks->avg('actual_hours');
    }

    private function calculateSuccessRate(): float
    {
        $totalTasks = $this->tasks()->count();
        if ($totalTasks === 0) return 0;

        $completedOnTime = $this->tasks()
            ->where('status', 'done')
            ->where('actual_hours', '<=', \DB::raw('estimated_hours * 1.2'))
            ->count();

        return ($completedOnTime / $totalTasks) * 100;
    }


    public function awards(): BelongsToMany
    {
        return $this->belongsToMany(Awards::class, 'programmer_awards')
            ->withPivot('earned_at', 'points_earned', 'metadata')
            ->withTimestamps();
    }

    public function hasAchievement($achievementId): bool
    {
        return $this->awards()
            ->where('awards.id', $achievementId)
            ->where('awards.type', Awards::TYPE_ACHIEVEMENT)
            ->exists();
    }
}
