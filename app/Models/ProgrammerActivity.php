<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgrammerActivity extends Model
{
    protected $fillable = [
        'programmer_id',
        'project_id',
        'commits_count',
        'code_lines_added',
        'code_lines_deleted',
        'chat_messages_count',
        'tasks_completed_count',
        'tasks_completed_on_time',
        'code_quality_score',
        'activity_date',
    ];

    protected $casts = [
        'activity_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
