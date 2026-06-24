<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmer_score_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programmer_id')->constrained('programmers')->onDelete('cascade');
            $table->integer('points');
            $table->string('reason');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programmer_score_logs');
    }
};
