<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;
    protected $fillable = [
        'company_id',
        'programmer_id',
        'status',
        'last_message_at',
        ];
    public function company():BelongsTo{
        return $this->belongsTo(Company::class);
    }
    public function programmer():BelongsTo{
        return $this->belongsTo(Programmer::class);
    }
    public function directMessage():HasMany{
        return $this->hasMany(DirectMessage::class);
    }
}
