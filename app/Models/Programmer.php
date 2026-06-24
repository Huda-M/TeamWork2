<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Programmer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
    'user_id',
        'user_name',
        'phone',
        'bio',
        'track',
        'avatar_url',
        'experience_level',
        'is_available',
        'profile_completed',
        'skills',
        'cover_image',
        'behance_url',
        'title',
        'specialty',
        'github_username',
        'portfolio_url',
        'linkedin_url',
        'twitter_url',
        'hourly_rate',
        'preferred_working_hours',
        'timezone',
        'current_team_id',
        'total_score',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'hourly_rate' => 'decimal:2',
        'preferred_working_hours' => 'array',
        'profile_completed' => 'boolean',
        'skills' => 'array',
    ];

    // ========== العلاقات ==========
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'programmer_skills')->withTimestamps();
    }

    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(Track::class, 'programmer_track')
            ->withPivot('progress_percentage', 'started_at', 'completed_at')
            ->withTimestamps();
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot('role', 'joined_at', 'left_at')
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function teamInvitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class, 'programmer_id');
    }

    public function sentInvitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class, 'invited_by');
    }

    public function scoreLogs()
    {
        return $this->hasMany(ProgrammerScoreLog::class);
    }

    /**
     * ✅ محسّن: بيدعم polymorphic source
     */
    public function addScore($points, $reason, $metadata = [], $source = null, $evaluatorId = null)
    {
        $log = $this->scoreLogs()->create([
            'points' => $points,
            'reason' => $reason,
            'metadata' => $metadata,
            'evaluator_id' => $evaluatorId,
        ]);

        // حدّث الـ total_score
        $this->increment('total_score', $points);

        return $log;
    }

    public function statistics(): HasOne
    {
        return $this->hasOne(ProgrammerStatistic::class);
    }

    public function programmerLevel(): HasOne
    {
        return $this->hasOne(ProgrammerLevel::class);
    }

    // ← العلاقات الجديدة
    public function createdTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'created_by');
    }

    public function createdProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'user_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'evaluated_id');
    }

    public function evaluationsGiven(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'evaluator_id');
    }

    // ========== أكسسوارات ==========
    public function getFullNameAttribute()
    {
        return $this->user->full_name;
    }

    public function getEmailAttribute()
    {
        return $this->user->email;
    }

    public function getIsInTeamAttribute(): bool
{
    $activeTeamsCount = $this->teams()->wherePivotNull('left_at')->count();
    return $activeTeamsCount >= 10; // ← 10 teams max
}

    public function getActiveTeamAttribute(): ?Team
    {
        return $this->teams()
            ->wherePivotNull('left_at')
            ->where('teams.status', 'active')
            ->first();
    }

    // ========== إدارة المستوى والخبرة ==========

    /**
     * تحديد المستوى (experience_level) بناءً على total_score
     */
    public function getExperienceLevelAttribute(): string
    {
        if ($this->total_score >= 2000) {
            return 'expert';
        }
        if ($this->total_score >= 1000) {
            return 'advanced';
        }
        if ($this->total_score >= 500) {
            return 'intermediate';
        }

        return 'beginner';
    }

    /**
     * إضافة نقاط (Stars) ورفع المستوى تلقائياً إذا تجاوز الحدود
     */
    public function addStars(int $points, string $reason, array $metadata = []): void
    {
        $oldLevel = $this->experience_level;
        $this->increment('total_score', $points);

        ProgrammerScoreLog::create([
            'programmer_id' => $this->id,
            'points' => $points,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);

        $newLevel = $this->experience_level;
        if ($oldLevel !== $newLevel) {
            logger("Programmer {$this->id} leveled up from $oldLevel to $newLevel");
        }
    }

    /**
     * حساب المستوى بناءً على إجمالي النجوم (points)
     */
    public function calculateLevelFromStars(): string
    {
        $score = $this->total_score;
        if ($score < 50) {
            return 'beginner';
        }
        if ($score < 200) {
            return 'junior';
        }
        if ($score < 450) {
            return 'senior';
        }

        return 'expert';
    }

    /**
     * التحقق من اكتمال البروفايل
     */
    public function isProfileCompleted(): bool
    {
        $requiredFields = ['user_name', 'phone', 'experience_level', 'track'];
        foreach ($requiredFields as $field) {
            if (empty($this->$field)) {
                return false;
            }
        }

        return true;
    }

    public function markProfileAsCompleted(): void
    {
        if (! $this->profile_completed && $this->isProfileCompleted()) {
            $this->update(['profile_completed' => true]);
        }
    }

    /**
     * إحصائيات عامة
     */
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
        if ($totalTasks === 0) {
            return 0;
        }
        $completedOnTime = $this->tasks()
            ->where('status', 'done')
            ->where('actual_hours', '<=', DB::raw('estimated_hours * 1.2'))
            ->count();

        return ($completedOnTime / $totalTasks) * 100;
    }

    // ========== سكوبات ==========
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeBySkill($query, $skillId)
    {
        return $query->whereHas('skills', function ($q) use ($skillId) {
            $q->where('skills.id', $skillId);
        });
    }

    public function scopeWithHighScore($query, $minScore = 500)
    {
        return $query->where('total_score', '>=', $minScore);
    }

    public function jopOffers()
    {
        return $this->hasMany(JopOffer::class);
    }

    public function AiTeam()
    {
        return $this->hasMany(AiTeam::class, 'user_id');
    }

    public function joinRequests()
    {
        return $this->hasMany(JoinRequest::class);
    }

    // public function getAvatarUrlAttribute($avatar_url)
    // {
    //     if (empty($avatar_url)) {
    //         return "";
    //     }

    //     if (filter_var($avatar_url, FILTER_VALIDATE_URL) || str_starts_with($avatar_url, 'http')) {
    //         return $avatar_url;
    //     }

    //     if (str_contains($avatar_url, '/')) {
    //         return Storage::disk('public')->url($avatar_url);
    //     }

    //     return Storage::disk('public')->url('avatars/' . $avatar_url);
    // }
}
