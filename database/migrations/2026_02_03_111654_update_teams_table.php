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
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('is_public')->default(true);
            $table->string('join_code', 8)->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('avatar_url')->nullable();
            $table->json('required_skills')->nullable();
            $table->json('preferred_skills')->nullable();
            $table->enum('experience_level', ['beginner', 'intermediate', 'advanced', 'expert'])->default('intermediate');
        });

    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'max_members',
                'min_members',
                'is_public',
                'join_code',
                'description',
                'avatar_url',
                'required_skills',
                'preferred_skills',
                'experience_level',
            ]);
        });

    }
};
