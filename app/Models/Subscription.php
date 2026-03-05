<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;
    protected $fillable = [
        'company_id',
        'price',
        'duration_days',
        'status',
        'start_date',
        'end_date',
    ];
    public function company():BelongsTo{
        return $this->belongsTo(Company::class);
    }
}
