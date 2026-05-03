<?php
// database/migrations/2026_04_30_000000_update_programmers_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('programmers', function (Blueprint $table) {
            // المستوى الخبروي (يتم تخزينه وليس حسابه)
            $table->enum('experience_level', ['beginner', 'intermediate', 'advanced', 'expert'])
                  ->nullable();
            $table->boolean('profile_completed')->default(false)->after('user_id');

            $table->json('skills')->nullable()->after('track');

            // $table->dropColumn('total_score');
        });
    }

    public function down()
    {
        Schema::table('programmers', function (Blueprint $table) {
            $table->dropColumn(['experience_level',  'skills']);
        });
    }
};
