<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'category_name',       // للتوافق القديم (تصنيف واحد)
        'categories',          // JSON جديد للتصنيفات المتعددة
        'required_tracks',     // JSON جديد للتراكات المطلوبة
        'github_url',
        'status',              // pending, active, completed, cancelled
        'estimated_duration_days',
        'user_id',             // منشئ المشروع (من جدول users)
        'created_by',          // اختياري: معرف المبرمج المنشئ
    ];

    protected $casts = [
        'categories'       => 'array',      // تحويل JSON تلقائياً إلى مصفوفة
        'required_tracks'  => 'array',
        'estimated_duration_days' => 'integer',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    // ===== العلاقات الأساسية =====

    /**
     * الفرق المرتبطة بهذا المشروع.
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * المستخدم المنشئ (جدول users).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * المهارات المطلوبة للمشروع (علاقة many-to-many مع جدول skills).
     */
    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'projects_skills');
    }

    /**
     * التقييمات الخاصة بالمشروع.
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /**
     * المهارات الخاصة بالمشروع (إذا كان لديك جدول pivot منفصل).
     */
    public function projectSkills(): HasMany
    {
        return $this->hasMany(ProjectSkill::class);
    }

    /**
     * أنشطة المبرمجين المرتبطة بالمشروع.
     */
    public function programmerActivities(): HasMany
    {
        return $this->hasMany(ProgrammerActivity::class);
    }

    // ===== Accessors إضافية =====

    /**
     * حالة المشروع (تعتمد على وجود فرق نشطة).
     */
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

    /**
     * تاريخ الانتهاء المتوقع.
     */
    public function getExpectedEndDateAttribute()
    {
        return $this->created_at->copy()->addDays($this->estimated_duration_days);
    }

    /**
     * هل المشروع متأخر عن الموعد.
     */
    public function getIsOverdueAttribute()
    {
        return now()->gt($this->expected_end_date);
    }

    /**
     * نسبة إنجاز المشروع بناءً على المهام.
     */
    public function getCompletionPercentageAttribute()
    {
        $totalTasks = $this->tasks()->count();
        if ($totalTasks == 0) {
            return 0;
        }

        $completedTasks = $this->tasks()->where('tasks.status', 'done')->count();
        return round(($completedTasks / $totalTasks) * 100);
    }

    /**
     * المهام المرتبطة بالمشروع عبر الفرق.
     */
    public function tasks()
    {
        return $this->hasManyThrough(Task::class, Team::class, 'project_id', 'team_id');
    }

    // ===== دوال إضافية (تم تعديلها أو إزالة التي تعتمد على الحقول المحذوفة) =====

    /**
     * ملاحظة: تم إزالة hasRoomForNewTeam() و getTotalAvailableSlots()
     * لأنها كانت تعتمد على team_size و max_teams المحذوفة.
     * إذا كنت لا تزال بحاجة إلى منطق مشابه، يمكنك إضافته بناءً على
     * عدد الأعضاء الحالي في الفرق أو قيمة max_team_size من جدول teams.
     */

    /**
     * مثال بديل: هل يمكن إنشاء فريق جديد للمشروع؟
     * يمكنك تعيين حد أقصى للفرق في جدول projects كعمود max_teams إذا احتجت.
     * حالياً نعيد true دائماً (بدون حدود).
     */
    public function hasRoomForNewTeam(): bool
    {
        // يمكنك وضع شرط هنا، مثلاً: عدد الفرق أقل من قيمة ثابتة أو من عمود في المشروع.
        return true;
    }

    /**
     * مثال بديل: إجمالي المقاعد المتاحة (لكل الفرق).
     * يحسب مجموع الأماكن الفارغة في جميع فرق المشروع.
     */
    public function getTotalAvailableSlots(): int
    {
        $totalVacancy = 0;
        foreach ($this->teams as $team) {
            $totalVacancy += $team->max_members - $team->activeMembers()->count();
        }
        return $totalVacancy;
    }
}
