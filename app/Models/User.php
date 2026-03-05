<?php

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
        'name',
        'user_name',
        'email',
        'password',
        'phone',
        'gender',
        'role',
        'bio',
        'country',
        'date_of_birth',
        'img_url',
        'avatar_url',
        'behance_url',
        'profile_completed',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'profile_completed' => 'boolean',
        ];
    }

    public function notifications()
    {
        return $this->morphMany(\Illuminate\Notifications\DatabaseNotification::class, 'notifiable')->orderBy('created_at', 'desc');
    }

    public function isProfileCompleted(): bool
    {
        $requiredFields = ['user_name', 'country', 'phone', 'gender', 'date_of_birth'];

        foreach ($requiredFields as $field) {
            if (empty($this->$field)) {
                return false;
            }
        }

        return true;
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

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function programmer(): HasOne
    {
        return $this->hasOne(Programmer::class);
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
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


    protected static function boot()
    {
        parent::boot();

        static::created(function ($user) {
            if ($user->role === 'programmer') {
                Programmer::create([
                    'user_id' => $user->id,
                    'specialty' => 'General',
                    'total_score' => 0,
                    'github_username' => '',
                    'is_available' => true,
                ]);
            }
        });
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
