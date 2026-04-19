<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ إنشاء مستخدم Admin افتراضي
        if (!User::where('email', 'admin@example.com')->exists()) {
            User::create([
                'full_name' => 'Admin User',  // ✅ استخدم full_name بدلاً من name
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ]);
        }

        $this->call([
            SkillSeeder::class,
            TrackSeeder::class,
            ProjectSeeder::class,
        ]);

        $this->command->info('✅ All seeders completed!');
    }
}
