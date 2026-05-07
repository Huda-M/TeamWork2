<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Programmer;

class ProgrammerSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('role', 'programmer')->get();

        foreach ($users as $user) {
            Programmer::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'user_name' => $user->full_name,
                    'phone' => '01000000000',
                    'track' => collect(['Frontend Developer', 'Backend Developer', 'Full Stack Developer', 'Mobile Developer'])->random(),
                    'experience_level' => collect(['beginner', 'intermediate', 'advanced', 'expert'])->random(),
                    'profile_completed' => true,
                    'skills' => json_encode(collect(['Laravel', 'React', 'Vue.js', 'Python', 'Node.js'])->random(3)->toArray()),
                ]
            );
        }
    }
}
