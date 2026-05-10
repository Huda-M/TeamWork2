<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Programmer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_name',
        'phone',
        'avatar_url',
        'cover_image',
        'behance_url',
        'title',
        'specialty',
        'total_score',
        'github_username',
        'portfolio_url',
        'linkedin_url',
        'twitter_url',
        'is_available',
        'hourly_rate',
        'preferred_working_hours',
        'timezone',
        'current_team_id',
        'profile_completed',
        'experience_level',
        'track',
        'skills',
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

    public function scoreLogs(): HasMany
    {
        return $this->hasMany(ProgrammerScoreLog::class);
    }

    public function statistics(): HasOne
    {
        return $this->hasOne(ProgrammerStatistic::class);
    }

    public function programmerLevel(): HasOne
    {
        return $this->hasOne(ProgrammerLevel::class);
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
        return $this->teams()->wherePivotNull('left_at')->exists();
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
        if ($this->total_score >= 2000) return 'expert';
        if ($this->total_score >= 1000) return 'advanced';
        if ($this->total_score >= 500) return 'intermediate';
        return 'beginner';
    }

    /**
     * إضافة نقاط (Stars) ورفع المستوى تلقائياً إذا تجاوز الحدود
     * @param int $points
     * @param string $reason
     * @param array $metadata
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
            // حدث تغيير المستوى – يمكن إضافة إشعار هنا
            logger("Programmer {$this->id} leveled up from $oldLevel to $newLevel");
        }
    }
    
    /**
     * حساب المستوى بناءً على إجمالي النجوم (points)
     * تستخدم لعرض المستوى كنص (Beginner, Junior, Senior...)
     */
    public function calculateLevelFromStars(): string
    {
        $score = $this->total_score;
        if ($score < 50) return 'beginner';
        if ($score < 200) return 'junior';
        if ($score < 450) return 'senior';
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
        if (!$this->profile_completed && $this->isProfileCompleted()) {
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
        if ($totalTasks === 0) return 0;
        $completedOnTime = $this->tasks()
            ->where('status', 'done')
            ->where('actual_hours', '<=', \DB::raw('estimated_hours * 1.2'))
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
}
