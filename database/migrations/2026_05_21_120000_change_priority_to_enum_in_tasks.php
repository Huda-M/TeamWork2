<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // تحديث القيم القديمة أولاً
        DB::table('tasks')->update([
            'priority' => DB::raw("
                CASE priority
                    WHEN 1 THEN 'low'
                    WHEN 2 THEN 'medium'
                    WHEN 3 THEN 'high'
                    ELSE 'medium'
                END
            ")
        ]);

        // تغيير نوع العمود لـ enum
        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('priority', ['low', 'medium', 'high'])
                  ->default('medium')
                  ->change();
        });
    }

    public function down(): void
    {
        // تحويل القيم مرة أخرى لأرقام
        DB::table('tasks')->update([
            'priority' => DB::raw("
                CASE priority
                    WHEN 'low' THEN 1
                    WHEN 'medium' THEN 2
                    WHEN 'high' THEN 3
                    ELSE 2
                END
            ")
        ]);

        Schema::table('tasks', function (Blueprint $table) {
            $table->integer('priority')->change();
        });
    }
};
