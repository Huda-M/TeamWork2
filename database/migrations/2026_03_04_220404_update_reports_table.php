<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('reports');

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('reporter_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('set null');

            $table->enum('report_type', [
                'harassment',
                'inappropriate_content',
                'spam',
                'fake_account',
                'cheating',
                'offensive_behavior',
                'other'
            ]);
            $table->text('description');
            $table->json('evidence')->nullable();

            $table->enum('admin_action', [
                'pending',
                'approved',
                'rejected',
                'warning_given',
                'suspension_applied'
            ])->default('pending');

            $table->text('admin_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamp('suspended_until')->nullable();
            $table->integer('suspension_count')->default(0);

            $table->timestamps();
            $table->index(['target_user_id', 'admin_action']);
            $table->index('created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_suspended')->default(false)->after('profile_completed');
            $table->timestamp('suspended_until')->nullable()->after('is_suspended');
            $table->integer('reports_count')->default(0)->after('suspended_until');
            $table->boolean('is_banned')->default(false)->after('reports_count');
            $table->timestamp('banned_at')->nullable()->after('is_banned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_suspended',
                'suspended_until',
                'reports_count',
                'is_banned',
                'banned_at'
            ]);
        });
    }
};
