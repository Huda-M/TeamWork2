<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // تحقق من وجود الأعمدة قبل حذفها
            $columns = Schema::getColumnListing('tasks');

            $columnsToDrop = [];

            if (in_array('parent_task_id', $columns)) {
                $table->dropForeign(['parent_task_id']);
                $columnsToDrop[] = 'parent_task_id';
            }

            if (in_array('dependencies', $columns)) {
                $columnsToDrop[] = 'dependencies';
            }

            if (in_array('subtasks', $columns)) {
                $columnsToDrop[] = 'subtasks';
            }

            if (in_array('split_by', $columns)) {
                $columnsToDrop[] = 'split_by';
            }

            if (in_array('split_at', $columns)) {
                $columnsToDrop[] = 'split_at';
            }

            if (in_array('is_parent', $columns)) {
                $columnsToDrop[] = 'is_parent';
            }

            if (in_array('original_task_id', $columns)) {
                $columnsToDrop[] = 'original_task_id';
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });

        Schema::dropIfExists('task_dependencies');
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // فقط أضف الأعمدة التي كانت موجودة بالفعل
            if (!Schema::hasColumn('tasks', 'parent_task_id')) {
                $table->foreignId('parent_task_id')->nullable()->constrained('tasks');
            }

            if (!Schema::hasColumn('tasks', 'dependencies')) {
                $table->json('dependencies')->nullable();
            }

            if (!Schema::hasColumn('tasks', 'subtasks')) {
                $table->json('subtasks')->nullable();
            }

            if (!Schema::hasColumn('tasks', 'split_by')) {
                $table->foreignId('split_by')->nullable()->constrained('programmers');
            }

            if (!Schema::hasColumn('tasks', 'split_at')) {
                $table->timestamp('split_at')->nullable();
            }

            if (!Schema::hasColumn('tasks', 'is_parent')) {
                $table->boolean('is_parent')->default(false);
            }

            if (!Schema::hasColumn('tasks', 'original_task_id')) {
                $table->foreignId('original_task_id')->nullable()->constrained('tasks');
            }
        });

        if (!Schema::hasTable('task_dependencies')) {
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
    }
};
