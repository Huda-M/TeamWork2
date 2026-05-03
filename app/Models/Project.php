<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'category_name',  // تأكد من الاسم الصحيح حسب الميجريشن
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
        'created_at' => 'datetime',
    ];

    // ===== العلاقات =====
    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'projects_skills');
    }


    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }

    public function getStatusAttribute()
{
    $hasActiveTeams = $this->teams()->where('teams.status', 'active')->exists();
    if ($hasActiveTeams) {
        return 'ongoing';
    }
    $hasAnyTeam = $this->teams()->exists();
    if ($hasAnyTeam) {
        return 'completed';
    }
    return 'pending';
}

    public function getExpectedEndDateAttribute()
    {
        return $this->created_at->copy()->addDays($this->estimated_duration_days);
    }

    /**
     * ما إذا كان المشروع قد انتهى (مقارنة باليوم الحالي)
     */
    public function getIsOverdueAttribute()
    {
        return now()->gt($this->expected_end_date);
    }

    public function getCompletionPercentageAttribute()
{
    $totalTasks = $this->tasks()->count();
    if ($totalTasks == 0) {
        return 0;
    }

    $completedTasks = $this->tasks()->where('tasks.status', 'done')->count();
    return round(($completedTasks / $totalTasks) * 100);
}

public function tasks()
{
    return $this->hasManyThrough(Task::class, Team::class, 'project_id', 'team_id');
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
    public function projectSkills():HasMany{
        return $this->hasMany(ProjectSkill::class);
    }

    public function programmerActivities():HasMany{
        return $this->hasMany(ProgrammerActivity::class);
    }

}
