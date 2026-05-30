<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Team;
use App\Models\Programmer;
use App\Models\TeamMember;

class TeamMemberSeeder extends Seeder
{
    public function run(): void
    {
        $programmers = Programmer::all();
        if ($programmers->count() < 3) {
            $this->command->warn('Not enough programmers, run ProgrammerSeeder first.');
            return;
        }

        $teams = Team::all();

        foreach ($teams as $team) {
            // Choose a random programmer as leader
            $leader = $programmers->random();
            TeamMember::updateOrCreate(
                ['team_id' => $team->id, 'programmer_id' => $leader->id],
                [
                    'role' => 'leader',
                    'joined_at' => now(),
                    'joined_by' => $leader->id,
                ]
            );

            // Add 2-4 members (excluding the leader)
            $candidates = $programmers->where('id', '!=', $leader->id);
            $membersCount = rand(2, min(4, $candidates->count()));
            $members = $candidates->random($membersCount);

            foreach ($members as $member) {
                TeamMember::updateOrCreate(
                    ['team_id' => $team->id, 'programmer_id' => $member->id],
                    [
                        'role' => 'member',
                        'joined_at' => now(),
                        'joined_by' => $leader->id,
                    ]
                );
            }
        }
    }
}
