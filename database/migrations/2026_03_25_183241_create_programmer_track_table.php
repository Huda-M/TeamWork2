<?php
// database/migrations/2026_03_25_000002_create_programmer_track_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmer_track', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programmer_id')->constrained()->onDelete('cascade');
            $table->foreignId('track_id')->constrained()->onDelete('cascade');
            $table->integer('progress_percentage')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['programmer_id', 'track_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programmer_track');
    }
};
