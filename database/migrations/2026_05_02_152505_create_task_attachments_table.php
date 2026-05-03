<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type')->nullable(); // mime type
            $table->integer('file_size')->nullable(); // bytes
            $table->foreignId('uploaded_by')->constrained('programmers');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_attachments');
    }
};
