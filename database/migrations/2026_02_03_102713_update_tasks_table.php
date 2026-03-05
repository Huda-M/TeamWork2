<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->integer('priority')->default(0)->index();
            $table->enum('complexity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->json('required_skills')->nullable();
            $table->json('assigned_skills')->nullable();

            $table->foreignId('parent_task_id')->nullable()->constrained('tasks');
            $table->json('dependencies')->nullable();
            $table->json('subtasks')->nullable();

            $table->integer('progress_percentage')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('programmers');

            $table->text('completion_notes')->nullable();
            $table->integer('quality_score')->nullable();
            $table->text('quality_feedback')->nullable();

            $table->foreignId('code_analysis_id')->nullable()->constrained('code_analyses');
            $table->boolean('needs_review')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->text('block_reason')->nullable();

            $table->foreignId('reassigned_from')->nullable()->constrained('programmers');
            $table->timestamp('reassigned_at')->nullable();

            $table->index(['team_id', 'status']);
            $table->index(['programmer_id', 'status']);
            $table->index(['deadline', 'status']);
            $table->index(['priority', 'status']);
            $table->index('complexity');
        });

        Schema::create('task_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->foreignId('changed_by')->constrained('programmers')->onDelete('cascade');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('change_type');
            $table->text('change_description');
            $table->timestamps();

            $table->index(['task_id', 'created_at']);
            $table->index('change_type');
        });

        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->foreignId('depends_on_task_id')->constrained('tasks')->onDelete('cascade');
            $table->enum('dependency_type', ['finish_start', 'start_start', 'finish_finish', 'start_finish']);
            $table->integer('lag_days')->default(0);
            $table->timestamps();

            $table->unique(['task_id', 'depends_on_task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('task_history');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'priority',
                'complexity',
                'required_skills',
                'assigned_skills',
                'parent_task_id',
                'dependencies',
                'subtasks',
                'progress_percentage',
                'started_at',
                'completed_at',
                'reviewed_at',
                'reviewed_by',
                'completion_notes',
                'quality_score',
                'quality_feedback',
                'code_analysis_id',
                'needs_review',
                'is_blocked',
                'block_reason',
                'reassigned_from',
                'reassigned_at'
            ]);

            $table->dropForeign(['parent_task_id']);
            $table->dropForeign(['reviewed_by']);
            $table->dropForeign(['code_analysis_id']);
            $table->dropForeign(['reassigned_from']);

            $table->dropIndex(['team_id', 'status']);
            $table->dropIndex(['programmer_id', 'status']);
            $table->dropIndex(['deadline', 'status']);
            $table->dropIndex(['priority', 'status']);
            $table->dropIndex(['complexity']);
        });
    }
};
