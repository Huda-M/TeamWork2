<?php
namespace Database\Seeders;

use App\Models\Team;
use App\Models\TeamStatistic;
use Illuminate\Database\Seeder;

class TeamStatisticSeeder extends Seeder
{
    public function run(): void
    {
        $teams = Team::all();

        foreach ($teams as $team) {
            for ($i = 0; $i < 30; $i++) {
                TeamStatistic::factory()->create([
                    'team_id' => $team->id,
                    'stat_date' => now()->subDays($i),
                ]);
            }

            TeamStatistic::updateTeamStats($team);
        }

        $this->command->info('Team statistics seeded successfully!');
        $this->command->info('Total statistics created: ' . TeamStatistic::count());
    }
}
