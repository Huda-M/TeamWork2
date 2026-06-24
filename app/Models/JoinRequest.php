<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JoinRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'programmer_id',
        'status',
        'responded_at',
        'responded_by',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function programmer()
    {
        return $this->belongsTo(Programmer::class);
    }

    public function responder()
    {
        return $this->belongsTo(Programmer::class, 'responded_by');
    }
}
