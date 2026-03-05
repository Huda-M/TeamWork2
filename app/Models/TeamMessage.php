<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMessage extends Model
{
    use HasFactory;
    protected $fillable = [
        'message_text',
        'message_type',
        'file_url',
        'programmer_id',
        'team_id',
    ];
    public function team():BelongsTo{
        return $this->belongsTo(Team::class);
    }
    public function programmer():BelongsTo{
        return $this->belongsTo(Programmer::class);
    }
}
