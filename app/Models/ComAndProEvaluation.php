<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComAndProEvaluation extends Model
{
    use HasFactory;
    protected $fillable = [
        'interview_id',
        'company_id',
        'programmer_id',
        'evaluation_type',
        'feedback',
        'professionalism',
        'rating',
    ];
    public function company():BelongsTo{
        return $this->belongsTo(Company::class);
    }
    public function programmer():BelongsTo{
        return $this->belongsTo(Programmer::class);
    }
    public function interview():BelongsTo{
        return $this->belongsTo(Interview::class);
    }
}
