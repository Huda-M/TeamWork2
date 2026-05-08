<?php
// database/migrations/2025_11_21_114720_create_programmers_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('user_name')->unique()->nullable();
            $table->string('track')->nullable();
            $table->string('phone')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('behance_url')->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar')->nullable();
            $table->integer('level')->default(0);
            $table->integer('stars')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programmers');
    }
};
