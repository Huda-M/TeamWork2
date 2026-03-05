<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('is_runoff')->default(false)->after('status');
            $table->integer('runoff_round')->nullable()->after('is_runoff');
            $table->json('runoff_candidates')->nullable()->after('runoff_round');
            $table->timestamp('leader_elected_at')->nullable()->after('runoff_candidates');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['is_runoff', 'runoff_round', 'runoff_candidates', 'leader_elected_at']);
        });
    }
};
