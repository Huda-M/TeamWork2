<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiTeam extends Model
{
    protected $fillable = [
        'user_id',
        'team_id'
    ];

    public function programmer(){
        return $this->belongsTo(Programmer::class, 'user_id');
    }

    public function team(){
        return $this->belongsTo(Team::class);
    }
}
