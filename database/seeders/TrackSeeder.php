<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Track;

class TrackSeeder extends Seeder
{
    public function run(): void
    {
        $tracks = [
            ['name' => 'Frontend Developer'],
            ['name' => 'Backend Developer'],
            ['name' => 'Full Stack Developer'],
            ['name' => 'Mobile Developer'],
            ['name' => 'DevOps Engineer'],
            ['name' => 'AI/ML Engineer'],
            ['name' => 'Database Administrator'],
            ['name' => 'UI/UX Designer'],
            ['name' => 'Security Engineer'],
            ['name' => 'Game Developer'],
            ['name' => 'Cloud Architect'],
            ['name' => 'Blockchain Developer'],
            ['name' => 'Data Scientist'],
            ['name' => 'QA Engineer'],
            ['name' => 'Product Manager'],
        ];

        foreach ($tracks as $track) {
            Track::create($track);
        }

        $this->command->info('✅ Tracks seeded successfully! (' . count($tracks) . ' tracks)');
    }
}
