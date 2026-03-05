<?php
// database/migrations/2026_03_05_000001_remove_subtasks_from_tasks_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['parent_task_id']);

            $table->dropColumn([
                'parent_task_id',
                'dependencies',
                'subtasks',
                'split_by',
                'split_at',
                'is_parent',
                'original_task_id',
            ]);
        });

        Schema::dropIfExists('task_dependencies');
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks');
            $table->json('dependencies')->nullable();
            $table->json('subtasks')->nullable();
            $table->foreignId('split_by')->nullable()->constrained('programmers');
            $table->timestamp('split_at')->nullable();
            $table->boolean('is_parent')->default(false);
            $table->foreignId('original_task_id')->nullable()->constrained('tasks');
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
};
