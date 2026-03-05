<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgrammerLevel extends Model
{
    use HasFactory;
    protected $fillable = [
        'programmer_id',
        'current_level',
        'current_xp',
        'xp_to_next_level',
        'ranking_position',
        ];
        public function programmer():BelongsTo{
            return $this->belongsTo(Programmer::class);
        }
}
