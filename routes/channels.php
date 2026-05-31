<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Team;

Broadcast::channel('team-chat.{teamId}', function ($user, $teamId) {
    return Team::where('id', $teamId)
        ->whereHas('teamMembers', function ($query) use ($user) {
            $query->where('programmer_id', $user->id);
        })
        ->exists();
});
