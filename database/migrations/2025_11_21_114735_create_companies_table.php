<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // بيانات الشركة الأساسية
            $table->string('company_name');
            $table->string('phone');
            $table->string('cr_number')->unique();
            $table->text('about')->nullable();
            $table->string('country');
            $table->string('location');
            $table->string('logo')->nullable();
            $table->json('social_links')->nullable();

            // حقول إضافية
            $table->string('industry');
            $table->date('subscription_end_date');

            $table->boolean('profile_completed')->default(false);
            $table->timestamps();

            // ✅ إضافة soft delete
            $table->softDeletes(); // يضيف عمود deleted_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};

