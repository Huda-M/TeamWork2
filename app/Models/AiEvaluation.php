<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiEvaluation extends Model
{
    use HasFactory;

    

    protected $fillable = [
        'project_id',
        'team_id',
        'evaluator_id',
        'evaluated_id',
        'overall_score',
        'breakdown',
        'explanation',
        'is_ai_generated',
    ];

    protected $casts = [
        'breakdown' => 'array',
        'overall_score' => 'decimal:2',
        'is_ai_generated' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function evaluator()
    {
        return $this->belongsTo(Programmer::class, 'evaluator_id');
    }

    public function evaluated()
    {
        return $this->belongsTo(Programmer::class, 'evaluated_id');
    }
}
