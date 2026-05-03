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
                    'track' => fake()->randomElement(['Frontend Developer', 'Backend Developer', 'Full Stack Developer', 'Mobile Developer']),
                    'experience_level' => fake()->randomElement(['beginner', 'intermediate', 'advanced', 'expert']),
                    'profile_completed' => true,
                    'skills' => json_encode(fake()->randomElements(['Laravel', 'React', 'Vue.js', 'Python', 'Node.js'], 3)),
                ]
            );
        }
    }
}
