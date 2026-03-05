<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'company_name',
        'industry',
        'size',
        'website',
        'subscription_end_date',
    ];
    public function subscribtions():HasMany{
        return $this->hasMany(Subscribtions::class);
    }
    public function payment():HasMany{
        return $this->hasMany(Payment::class);
    }
    public function user():BelongsTo{
        return $this->belongsTo(User::class);
    }
    public function interview():HasMany{
        return $this->hasMany(Interview::class);
    }
    public function ComAndProEvaluations():HasMany{
        return $this->hasMany(ComAndProEvaluation::class,'company_id');
    }
    public function conversation():HasMany{
        return $this->hasMany(Conversation::class);
    }
}
