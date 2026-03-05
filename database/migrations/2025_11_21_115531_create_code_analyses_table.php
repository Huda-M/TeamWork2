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
        Schema::create('code_analyses', function (Blueprint $table) {
            $table->id();
            // $table->foreignId('programmer_id')->constrained();
            // $table->foreignId('task_id')->constrained();
            // $table->foreignId('project_id')->constrained();
            // $table->integer('code_smells');
            // $table->integer('critical_issues');
            // $table->integer('total_issues');
            // $table->integer('overall_score');
            // $table->integer('laravel_pint_score');
            // $table->integer('phpstan_score');
            // $table->integer('php_md_score');
            // $table->integer('php_cs_score');
            // $table->enum('status',['pending','success','failed']);
            // $table->string('branch');
            // $table->text('commit_hash');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('code_analyses');
    }
};
