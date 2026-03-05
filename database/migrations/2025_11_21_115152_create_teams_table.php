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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('formation_type',['random', 'manual', 'mixed']);
$table->enum('status',['active', 'completed', 'disbanded', 'forming', 'voting']);            $table->foreignId('project_id')->nullable()->constrained();
            $table->timestamp('disbanded_at')->nullable();
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('programmers');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
