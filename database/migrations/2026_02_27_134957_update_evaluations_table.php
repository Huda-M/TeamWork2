<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('evaluator_id')->constrained('programmers')->onDelete('cascade');
            $table->foreignId('evaluated_id')->constrained('programmers')->onDelete('cascade');

            $table->integer('technical_skills')->unsigned()->min(1)->max(10);
            $table->integer('communication')->unsigned()->min(1)->max(10);
            $table->integer('teamwork')->unsigned()->min(1)->max(10);
            $table->integer('problem_solving')->unsigned()->min(1)->max(10);
            $table->integer('reliability')->unsigned()->min(1)->max(10);
            $table->integer('code_quality')->unsigned()->min(1)->max(10);

            $table->decimal('average_score', 3, 2)->nullable();
            $table->text('strengths')->nullable();
            $table->text('areas_for_improvement')->nullable();
            $table->text('feedback')->nullable();

            $table->boolean('is_anonymous')->default(true);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('submitted_at')->nullable();

            $table->unique(['project_id', 'team_id', 'evaluator_id', 'evaluated_id'], 'unique_evaluation');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
