<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'project_id',
        'task_id',
        'team_id',
        'interview_id',
        'is_read',
        'title',
        'message',
        'type',
        'related_entity_type',
    ];
    public function user():BelongsTo{
        return $this->belongsTo(User::class);
    }
}
