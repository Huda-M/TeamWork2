<?php

namespace Database\Seeders;

use App\Models\Programmer;
use App\Models\ProgrammerLevel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProgrammerLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $programmers = Programmer::whereHas('user', function ($query) {
            $query->where('role', 'programmer');
        })->get();
        foreach ($programmers as $programmer) {
            ProgrammerLevel::create([
                'programmer_id' => $programmer->id,
                'current_level' => 1,
                'current_xp' => 0,
                'xp_to_next_level' => 100,
            ]);
        }
    }
}
