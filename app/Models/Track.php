<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Track extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function programmers(): BelongsToMany
    {
        return $this->belongsToMany(Programmer::class, 'programmer_track')
                    ->withPivot('progress_percentage', 'started_at', 'completed_at')
                    ->withTimestamps();
    }
}
