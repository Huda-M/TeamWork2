<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('voter_id')->constrained('programmers')->onDelete('cascade');
            $table->foreignId('candidate_id')->constrained('programmers')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['team_id', 'voter_id']);
        });
    }

    public function down(): void
    {
        Schema::table('team_member', function (Blueprint $table) {

        });
    }
};
