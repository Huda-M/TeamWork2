<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            
            $table->string('title');                           
            $table->text('description');                       
            
            // التصنيفات (للدعم القديم والجديد)
            $table->string('category_name')->nullable();       // تصنيف واحد (قديم)
            $table->json('categories')->nullable();            // مصفوفة تصنيفات متعددة (جديد)
            $table->json('required_tracks')->nullable();       // التراكات المطلوبة كمصفوفة نصوص
            
            // روابط خارجية
            $table->string('github_url')->nullable();          // رابط GitHub
            
            // حالة المشروع ومدة العمل
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])->default('pending');
            $table->integer('estimated_duration_days')->default(30); // المدة المتوقعة بالأيام
            
            // العلاقات
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // منشئ المشروع (من جدول users)
            $table->foreignId('created_by')->nullable()->constrained('programmers')->onDelete('set null'); // معرف المبرمج المنشئ
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
