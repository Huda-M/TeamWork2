<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluation extends Model
{
    use HasFactory;

    protected $table = 'evaluations'; // تأكيد اسم الجدول

    protected $fillable = [
        'project_id',
        'team_id',
        'evaluator_id',
        'evaluated_id',
        'technical_skills',
        'communication',
        'teamwork',
        'problem_solving',
        'reliability',
        'code_quality',
        'average_score',
        'strengths',
        'areas_for_improvement',
        'feedback',
        'is_anonymous',
        'is_completed',
        'submitted_at',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'is_completed' => 'boolean',
        'submitted_at' => 'datetime',
        'average_score' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(Programmer::class, 'evaluator_id');
    }

    public function evaluated(): BelongsTo
    {
        return $this->belongsTo(Programmer::class, 'evaluated_id');
    }
}
