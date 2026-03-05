<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DirectMessage extends Model
{
    use HasFactory;
    protected $fillable = [
        'conversation_id',
        'user_id',
        'message_text',
        'is_read',
        ];
    public function conversation():BelongsTo{
        return $this->belongsTo(Conversation::class);
    }
    public function user():BelongsTo{
        return $this->belongsTo(User::class);
    }
}
