<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {

Schema::create('team_evaluations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained();
    $table->foreignId('evaluator_id')->constrained('programmers');
    $table->integer('collaboration_score');
    $table->integer('communication_score');
    $table->integer('productivity_score');
    $table->text('feedback');
    $table->timestamps();
});

Schema::create('team_meetings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained();
    $table->string('title');
    $table->text('agenda');
    $table->datetime('scheduled_at');
    $table->integer('duration_minutes');
    $table->string('meeting_link')->nullable();
    $table->json('participants');
    $table->text('notes')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_evaluations');
        Schema::dropIfExists('team_meetings');
    }
};
