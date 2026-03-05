<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAuth extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'provider_type',
        'provider_user_id',
        'provider_email',
        'provider_name',
        'access_token',
        'refresh_token',
        'token_expires_at',
    ];
    public function user():BelongsTo{
        return $this->belongsTo(User::class);
    }
    protected $casts = [
        'token_expires_at' => 'datetime',
    ];
}
