<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ إضافة أعمدة الإيقاف والحظر إلى جدول users
        Schema::table('users', function (Blueprint $table) {
            // تحقق من وجود الأعمدة قبل إضافتها
            if (!Schema::hasColumn('users', 'is_suspended')) {
                $table->boolean('is_suspended')->default(false)->after('role');
            }

            if (!Schema::hasColumn('users', 'suspended_until')) {
                $table->timestamp('suspended_until')->nullable()->after('is_suspended');
            }

            if (!Schema::hasColumn('users', 'reports_count')) {
                $table->integer('reports_count')->default(0)->after('suspended_until');
            }

            if (!Schema::hasColumn('users', 'is_banned')) {
                $table->boolean('is_banned')->default(false)->after('reports_count');
            }

            if (!Schema::hasColumn('users', 'banned_at')) {
                $table->timestamp('banned_at')->nullable()->after('is_banned');
            }
        });

        // ✅ تحديث جدول reports
        Schema::table('reports', function (Blueprint $table) {
            // أضف الأعمدة إذا لم تكن موجودة
            if (!Schema::hasColumn('reports', 'report_type')) {
                $table->enum('report_type', [
                    'harassment',
                    'inappropriate_content',
                    'spam',
                    'fake_account',
                    'cheating',
                    'offensive_behavior',
                    'other'
                ])->after('description');
            }

            if (!Schema::hasColumn('reports', 'evidence')) {
                $table->json('evidence')->nullable()->after('report_type');
            }

            if (!Schema::hasColumn('reports', 'admin_action')) {
                $table->enum('admin_action', [
                    'pending',
                    'approved',
                    'rejected',
                    'warning_given',
                    'suspension_applied'
                ])->default('pending')->after('evidence');
            }

            if (!Schema::hasColumn('reports', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('admin_action');
            }

            if (!Schema::hasColumn('reports', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('admin_notes');
            }

            if (!Schema::hasColumn('reports', 'suspended_until')) {
                $table->timestamp('suspended_until')->nullable()->after('reviewed_at');
            }

            if (!Schema::hasColumn('reports', 'suspension_count')) {
                $table->integer('suspension_count')->default(0)->after('suspended_until');
            }
        });
    }

    public function down(): void
    {
        // إزالة الأعمدة من users
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_suspended',
                'suspended_until',
                'reports_count',
                'is_banned',
                'banned_at'
            ]);
        });

        // إزالة الأعمدة من reports
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn([
                'report_type',
                'evidence',
                'admin_action',
                'admin_notes',
                'reviewed_at',
                'suspended_until',
                'suspension_count'
            ]);
        });
    }
};
