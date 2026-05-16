<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JopOffer extends Model
{
    protected $fillable = [
        'title',
        'company_name',
        'description',
        'salary_range',
        'job_type',
        'work_type',
    ];
    
    public function programmer()
    {
        return $this->belongsTo(Programmer::class);
    }
}
