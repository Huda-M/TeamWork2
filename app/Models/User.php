<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'full_name',
        'email',
        'password',
        'role',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // العلاقات
    public function programmer(): HasOne
    {
        return $this->hasOne(Programmer::class);
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    // الحصول على الملف الشخصي
    public function getProfileAttribute()
    {
        if ($this->role === 'programmer' && $this->programmer) {
            return $this->programmer;
        }

        if ($this->role === 'company' && $this->company) {
            return $this->company;
        }

        return null;
    }

    // التحقق من اكتمال البروفايل
    public function isProfileCompleted(): bool
    {
        $profile = $this->profile;

        if (!$profile) return false;

        $requiredFields = ['user_name', 'phone'];

        foreach ($requiredFields as $field) {
            if (empty($profile->$field)) {
                return false;
            }
        }

        return true;
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::created(function ($user) {
            if ($user->role === 'programmer') {
                $user->programmer()->create([]);
            } elseif ($user->role === 'company') {
                $user->company()->create([]);
            }
        });
    }



    public function notifications()
    {
        return $this->morphMany(\Illuminate\Notifications\DatabaseNotification::class, 'notifiable')->orderBy('created_at', 'desc');
    }

    public function markProfileAsCompleted(): void
    {
        if ($this->isProfileCompleted() && !$this->profile_completed) {
            $this->update(['profile_completed' => true]);
        }
    }


    public function userAuth(): HasOne
    {
        return $this->hasOne(UserAuth::class);
    }

    public function reported(): HasMany
    {
        return $this->hasMany(Report::class, 'target_user_id');
    }

    public function reporter(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_user_id');
    }

    public function admin(): HasMany
    {
        return $this->hasMany(Report::class, 'admin_id');
    }

    public function directMessage(): HasMany
    {
        return $this->hasMany(DirectMessage::class);
    }

    public function teamMemberships()
    {
        return $this->hasManyThrough(
            TeamMember::class,
            Programmer::class,
            'user_id',
            'programmer_id',
            'id',
            'id'
        );
    }

    public function getProgrammerTeamsAttribute()
    {
        return $this->programmer ? $this->programmer->teams : collect();
    }

    public function getCompanyProjectsAttribute()
    {
        return $this->company ? $this->company->projects : collect();
    }

    public function scopeProgrammers($query)
    {
        return $query->where('role', 'programmer');
    }

    public function scopeWithProgrammerProfile($query)
    {
        return $query->whereHas('programmer');
    }

    public function scopeByUsername($query, $username)
    {
        return $query->where('user_name', $username);
    }
}
