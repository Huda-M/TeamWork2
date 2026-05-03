<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category_name');
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])
                  ->default('pending');
            $table->string('difficulty')->nullable();
            $table->integer('estimated_duration_days');
            $table->integer('max_team_size');
            $table->integer('num_of_team');
            $table->text('description');
            $table->foreignId('user_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
