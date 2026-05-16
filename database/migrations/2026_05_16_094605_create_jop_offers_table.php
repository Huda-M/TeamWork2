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
        Schema::create('jop_offers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('company_name');
            $table->foreignId('programmer_id')->constrained()->cascadeOnDelete();
            $table->text('description');
            $table->string('salary_range');
            $table->enum('job_type',['full-time','part-time','freelance']);
            $table->enum('work_type', ['on-site', 'remote', 'hybrid']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jop_offers');
    }
};
