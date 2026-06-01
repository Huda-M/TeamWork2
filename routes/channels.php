<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Team;

Broadcast::channel('team-chat.{teamId}', function ($user, $teamId) {
    $programmer = $user->programmer;

    if (! $programmer) {
        return false;
    }

    return Team::find($teamId)?->isMember($programmer->id) ?? false;
});
