<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Programmer;
use App\Models\Team;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Faker\Factory as FakerFactory;

class DatabaseSeeder extends Seeder
{
    protected $faker;

    public function __construct()
    {
        $this->faker = FakerFactory::create();
    }

    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        User::truncate();
        Programmer::truncate();
        Team::truncate();
        Task::truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // إنشاء البيانات
        // $this->createAdminUsers();
        // $programmers = $this->createProgrammers(50);
        // $teams = $this->createTeams(15, $programmers);
        // $this->createTasksForTeams($teams);
        // $this->createStatistics($teams);

        $this->command->info('✅ Database seeded successfully!');
    }

}
