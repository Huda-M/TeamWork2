<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgrammersSkills extends Model
{
    use HasFactory;
    protected $fillable = [
        'programmer_id',
        'skill_id',
    ];
    public function programmer():BelongsTo{
        return $this->belongsTo(Programmer::class);
    }
    public function skill():BelongsTo{
        return $this->belongsTo(Skill::class);
    }
}
