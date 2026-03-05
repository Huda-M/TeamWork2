<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Interview extends Model
{
    use HasFactory;
    protected $fillable = [
        'company_id',
        'programmer_id',
        'feedback',
        'status',
    ];
    public function company():BelongsTo{
        return $this->belongsTo(Company::class,'company_id');
    }
    public function programmer():BelongsTo{
        return $this->belongsTo(Programmer::class,'programmer_id');
    }
    public function ComAndProEvaluation():HasMany{
        return $this->hasMany(ComAndProEvaluation::class,'interview_id');
    }
}
