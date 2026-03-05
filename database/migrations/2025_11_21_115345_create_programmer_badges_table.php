<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {

Schema::create('badges', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->text('description');
    $table->string('icon_url')->nullable();
    $table->enum('type', ['achievement', 'skill', 'teamwork']);
    $table->integer('required_score')->default(0);
    $table->timestamps();
});
Schema::create('programmer_badges', function (Blueprint $table) {
    $table->id();
    $table->foreignId('programmer_id')->constrained();
    $table->foreignId('badge_id')->constrained('badges');
    $table->timestamp('earned_at');
    $table->timestamps();
});

    }


    public function down(): void
    {
        Schema::dropIfExists('badges');
        Schema::dropIfExists('programmer_badges');

    }
};
