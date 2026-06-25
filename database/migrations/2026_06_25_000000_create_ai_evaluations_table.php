<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('evaluator_id')->constrained('programmers')->onDelete('cascade');
            $table->foreignId('evaluated_id')->constrained('programmers')->onDelete('cascade');
            $table->decimal('overall_score', 4, 2);
            $table->json('breakdown')->nullable(); // technical_skills, communication, teamwork, etc.
            $table->text('explanation')->nullable();
            $table->boolean('is_ai_generated')->default(true);
            $table->timestamps();

            $table->unique(['project_id', 'evaluated_id'], 'unique_ai_eval_per_programmer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_evaluations');
    }
};
