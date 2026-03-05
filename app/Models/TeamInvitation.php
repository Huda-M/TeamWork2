<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'programmer_id',
        'invited_by',
        'message',
        'status',
        'expires_at',
        'accepted_at',
        'declined_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function programmer(): BelongsTo
    {
        return $this->belongsTo(Programmer::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(Programmer::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && now()->greaterThan($this->expires_at);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    public function accept(): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        \DB::transaction(function () {
            TeamMember::create([
                'team_id' => $this->team_id,
                'programmer_id' => $this->programmer_id,
                'role' => 'member',
                'joined_at' => now(),
                'joined_by' => $this->invited_by,
                'invitation_id' => $this->id,
            ]);

            $this->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            $this->notifyTeamMembers();
        });

        return true;
    }
    public function isModifiable(): bool
{
    return $this->status === 'pending' && !$this->isExpired();
}

    public function inviterUser()
    {
        return $this->hasOneThrough(
            User::class,
            Programmer::class,
            'id',
            'id',
            'invited_by',
            'user_id'
        );
    }

    public function invitedUser()
    {
        return $this->hasOneThrough(
            User::class,
            Programmer::class,
            'id',
            'id',
            'programmer_id',
            'user_id'
        );
    }
}
