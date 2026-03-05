<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'formation_type',
        'status',
        'project_id',
        'disbanded_at',
        'is_public',
        'join_code',
        'description',
        'avatar_url',
        'required_skills',
        'preferred_skills',
        'experience_level',
        'created_by'
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'disbanded_at' => 'datetime',
        'required_skills' => 'array',
        'preferred_skills' => 'array',
    ];

     public function getMaxMembersAttribute()
    {
        return $this->project->team_size;
    }

    public function getMinMembersAttribute()
    {
        return $this->project->min_team_size;
    }

    public function hasVacancy()
    {
        return $this->activeMembers()->count() < $this->max_members;
    }

    public function getAvailableSlots()
    {
        return $this->max_members - $this->activeMembers()->count();
    }

    public function isFull()
    {
        return $this->activeMembers()->count() >= $this->max_members;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function teamMessages(): HasMany
    {
        return $this->hasMany(TeamMessage::class);
    }

    public function programmers(): BelongsToMany
    {
        return $this->belongsToMany(Programmer::class, 'team_members')
                    ->withPivot('role', 'joined_at', 'left_at')
                    ->withTimestamps();
    }

    public function leader(): HasOne
    {
        return $this->hasOne(TeamMember::class)->where('role', 'leader');
    }

    public function activeMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class)->whereNull('left_at');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }



    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeHasVacancies($query)
    {
        return $query->whereColumn('max_members', '>', function($subQuery) {
            $subQuery->selectRaw('COUNT(*)')
                    ->from('team_members')
                    ->whereColumn('team_members.team_id', 'teams.id')
                    ->whereNull('left_at');
        });
    }

    public function scopeByProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeByExperienceLevel($query, $level)
    {
        return $query->where('experience_level', $level);
    }


    public function isMember($programmerId): bool
    {
        return $this->activeMembers()->where('programmer_id', $programmerId)->exists();
    }

    public function isLeader($programmerId): bool
    {
        return $this->activeMembers()
                    ->where('programmer_id', $programmerId)
                    ->where('role', 'leader')
                    ->exists();
    }

    public function getMembersCount(): int
    {
        return $this->activeMembers()->count();
    }

    public function generateJoinCode(): string
    {
        $code = strtoupper(substr(md5(uniqid()), 0, 8));
        $this->update(['join_code' => $code]);
        return $code;
    }

    public function disband(): void
    {
        DB::transaction(function () {
            $this->update([
                'status' => 'disbanded',
                'disbanded_at' => now(),
            ]);

            $this->activeMembers()->update(['left_at' => now()]);

            $this->tasks()->whereNotIn('status', ['done', 'cancelled'])->update([
                'status' => 'cancelled',
                'completed_at' => now(),
            ]);
        });
    }

    public function getPerformanceReport($date = null): array
    {
        $statDate = $date ? \Carbon\Carbon::parse($date) : now();
        $statistics = $this->statistics()->whereDate('stat_date', $statDate)->first();

        if ($statistics) {
            return $statistics->getPerformanceReport();
        }

        return [
            'team' => $this->name,
            'date' => $statDate->format('Y-m-d'),
            'message' => 'No statistics available for this date',
            'members_count' => $this->getMembersCount(),
            'active_tasks' => $this->tasks()->whereNotIn('status', ['done', 'cancelled'])->count(),
        ];
    }


}
