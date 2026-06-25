<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmer_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programmer_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->integer('commits_count')->default(0);
            $table->integer('code_lines_added')->default(0);
            $table->integer('code_lines_deleted')->default(0);
            $table->integer('chat_messages_count')->default(0);
            $table->integer('tasks_completed_count')->default(0);
            $table->integer('tasks_completed_on_time')->default(0);
            $table->integer('code_quality_score')->default(0);
            $table->date('activity_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programmer_activities');
    }
};
