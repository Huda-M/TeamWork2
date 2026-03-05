<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluation extends Model
{
    use HasFactory;
    protected $fillable = [
        'evaluator_id',
        'evaluated_id',
        'project_id',
        'technical_quality',
        'timeliness',
        'teamwork',
        'communication',
        'final_score',
        'comments',
        ];
        public function project():BelongsTo{
            return $this->belongsTo(Project::class);
        }
        public function evaluator():BelongsTo{
            return $this->belongsTo(Programmer::class);
        }
        public function evaluated():BelongsTo{
            return $this->belongsTo(Programmer::class);
        }
}
