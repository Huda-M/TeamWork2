<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'category',
        'difficulty',
        'estimated_duration_days',
        'team_size',
        'min_team_size',
        'max_teams',
        'user_id',
    ];

    protected $casts = [
        'team_size' => 'integer',
        'min_team_size' => 'integer',
        'max_teams' => 'integer',
    ];

    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    public function hasRoomForNewTeam()
    {
        return $this->teams()->count() < $this->max_teams;
    }

    public function getTotalAvailableSlots()
    {
        $totalMembers = $this->teams()
            ->withCount('activeMembers')
            ->get()
            ->sum('active_members_count');

        $totalCapacity = $this->teams()->count() * $this->team_size;

        return $totalCapacity - $totalMembers;
    }

    public function tasks():HasMany{
        return $this->hasMany(Task::class);
    }
    public function projectSkills():HasMany{
        return $this->hasMany(ProjectSkill::class);
    }

    public function user():BelongsTo{
        return $this->belongsTo(User::class);
    }
    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'projects_skills');
    }
    public function programmerActivities():HasMany{
        return $this->hasMany(ProgrammerActivity::class);
    }
    public function evaluations():HasMany{
        return $this->hasMany(Evaluation::class);
    }

}
