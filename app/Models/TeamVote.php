<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'voter_id',
        'candidate_id'
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function voter()
    {
        return $this->belongsTo(Programmer::class, 'voter_id');
    }

    public function candidate()
    {
        return $this->belongsTo(Programmer::class, 'candidate_id');
    }
}
