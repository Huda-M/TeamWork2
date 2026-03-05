<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodeAnalyses extends Model
{
    use HasFactory;
    protected $fillable = [
        'programmer_id',
        'task_id',
        'project_id',
        'code_smells',
        'critical_issues',
        'total_issues',
        'overall_score',
        'laravel_pint_score',
        'phpstan_score',
        'php_md_score',
        'php_cs_score',
        'status',
        'branch',
        'commit_hash',
        ];
        public function task():BelongsTo{
            return $this->belongsTo(Task::class);
        }
        public function programmer():BelongsTo{
            return $this->belongsTo(Programmer::class);
        }
        public function project():BelongsTo{
            return $this->belongsTo(Project::class);
        }
}
