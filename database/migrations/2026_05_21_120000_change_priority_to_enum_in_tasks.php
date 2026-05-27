<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // تحويل priority من integer إلى enum ('low', 'medium', 'high')
        Schema::table('tasks', function (Blueprint $table) {
            // في PostgreSQL: تغيير النوع باستخدام SQL خام
            DB::statement("ALTER TABLE tasks ALTER COLUMN priority TYPE VARCHAR(20) USING
                CASE priority
                    WHEN 1 THEN 'low'
                    WHEN 2 THEN 'medium'
                    WHEN 3 THEN 'high'
                    ELSE 'medium'
                END");
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            DB::statement("ALTER TABLE tasks ALTER COLUMN priority TYPE INTEGER USING
                CASE priority
                    WHEN 'low' THEN 1
                    WHEN 'medium' THEN 2
                    WHEN 'high' THEN 3
                    ELSE 2
                END");
        });
    }
};
