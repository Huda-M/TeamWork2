<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgrammerScoreLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'programmer_id',
        'points',
        'reason',
        'metadata',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'points' => 'integer',
        'metadata' => 'array',
    ];

    public function programmer(): BelongsTo
    {
        return $this->belongsTo(Programmer::class);
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function scopePositive($query)
    {
        return $query->where('points', '>', 0);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
