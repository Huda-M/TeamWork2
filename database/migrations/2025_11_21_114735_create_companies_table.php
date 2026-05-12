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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // بيانات الشركة الأساسية
            $table->string('company_name');
            $table->string('phone');                      // رقم الهاتف
            $table->string('cr_number')->unique();        // السجل التجاري (فريد)
            $table->text('about')->nullable();            // نبذة عن الشركة
            $table->string('country');                    // الدولة
            $table->string('location');                   // العنوان التفصيلي
            $table->string('logo')->nullable();           // شعار الشركة
            $table->json('social_links')->nullable();     // روابط السوشيال ميديا
            
            // حقول إضافية سابقة
            $table->string('industry');
            $table->string('size');
            $table->string('website');
            $table->date('subscription_end_date');
            
            $table->boolean('profile_completed')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
