<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgrammerActivity extends Model
{
    use HasFactory;
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
        public function programmer():BelongsTo{
            return $this->belongsTo(Programmer::class);
        }
        public function project():BelongsTo{
            return $this->belongsTo(Project::class);
        }
}
