<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'company_name',
        'phone',                 // جديد
        'cr_number',             // جديد
        'about',                 // جديد
        'country',               // جديد
        'location',              // جديد
        'logo',                  // جديد
        'social_links',          // جديد
        'profile_completed',     // جديد
        'industry',
    ];

    protected $casts = [
        'social_links' => 'array',
        'profile_completed' => 'boolean',
    ];

    protected $appends = [
        'logo',
    ];

    // العلاقات (كما هي موجودة)
    public function subscription(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    public function payment(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function interview(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    public function ComAndProEvaluations(): HasMany
    {
        return $this->hasMany(ComAndProEvaluation::class, 'company_id');
    }

    public function conversation(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    // إضافة accessor للحصول على رابط الشعار الكامل
    public function getLogoAttribute(): ?string
    {
        return $this->logo ? asset('storage/'.$this->logo) : null;
    }
}
