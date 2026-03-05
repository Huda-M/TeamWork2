<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMember extends Model
{
    protected $fillable = [
        'team_id',
        'programmer_id',
        'role',
        'joined_at',
        'left_at',
        'joined_by',
        'invitation_id',
        'votes_count'
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function programmer()
    {
        return $this->belongsTo(Programmer::class);
    }

    public function votesReceived()
    {
        return $this->hasMany(TeamVote::class, 'candidate_id', 'programmer_id');
    }

    public function votesGiven()
    {
        return $this->hasMany(TeamVote::class, 'voter_id', 'programmer_id');
    }




    public function inviter(): BelongsTo
    {
        return $this->belongsTo(Programmer::class, 'joined_by');
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(TeamInvitation::class);
    }

    public function isActive(): bool
    {
        return is_null($this->left_at);
    }

    public function leave(): void
    {
        $this->update(['left_at' => now()]);

        if ($this->role === 'leader') {
            $this->assignNewLeader();
        }
    }

    private function assignNewLeader(): void
    {
        $newLeader = $this->team->activeMembers()
            ->where('programmer_id', '!=', $this->programmer_id)
            ->oldest()
            ->first();

        if ($newLeader) {
            $newLeader->update(['role' => 'leader']);
        }
    }
}
