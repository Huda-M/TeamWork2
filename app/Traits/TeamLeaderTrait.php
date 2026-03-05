<?php
namespace App\Traits;

use App\Models\Team;

trait TeamLeaderTrait
{
    protected function isTeamLeader(Team $team, int $programmerId): bool
    {
        return $team->isLeader($programmerId);
    }

    protected function authorizeTeamLeader(Team $team): void
    {
        $user = auth()->user();
        $programmer = $user->programmer;

        if (!$this->isTeamLeader($team, $programmer->id)) {
            abort(403, 'Only team leader can perform this action');
        }
    }

    protected function getLedTeams()
    {
        $user = auth()->user();
        $programmer = $user->programmer;

        return $programmer->ledTeams();
    }
}
