<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained();
            $table->foreignId('programmer_id')->constrained();
            $table->enum('role',['leader', 'member']);
            $table->integer('votes_count')->default(0);
            $table->timestamp('left_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->foreignId('joined_by')->nullable()->constrained('programmers');
            $table->foreignId('invitation_id')->nullable()->constrained('team_invitations');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
