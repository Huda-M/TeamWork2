<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasFactory;
    protected $fillable = [
        'target_user_id',
        'reporter_user_id',
        'admin_id',
        'admin_action',
        'description',
        'status',
        'report_type',
        ];
        public function targetUser():BelongsTo{
            return $this->belongsTo(User::class,'target_user_id');
        }
        public function reporterUser():BelongsTo{
            return $this->belongsTo(User::class,'reporter_user_id');
        }
        public function admin():BelongsTo{
            return $this->belongsTo(User::class,'admin_id');
        }
}
