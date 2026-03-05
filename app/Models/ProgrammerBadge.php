<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgrammerBadge extends Model
{
    use HasFactory;
    protected $fillable = [
        'programmer_id',
        'badge_name',
        ];
        public function programmer():BelongsTo{
            return $this->belongsTo(Programmer::class);
        }
}
