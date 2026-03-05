<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->integer('team_size')->default(10)->after('description');
            $table->integer('min_team_size')->default(3)->after('team_size');
            $table->integer('max_teams')->default(5)->after('min_team_size');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['team_size', 'min_team_size', 'max_teams']);
        });
    }
};
