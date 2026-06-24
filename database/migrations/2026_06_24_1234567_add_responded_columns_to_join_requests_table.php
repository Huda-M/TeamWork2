<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('join_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('join_requests', 'responded_at')) {
                $table->timestamp('responded_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('join_requests', 'responded_by')) {
                $table->foreignId('responded_by')->nullable()->constrained('programmers')->onDelete('set null')->after('responded_at');
            }
        });
    }

    public function down()
    {
        Schema::table('join_requests', function (Blueprint $table) {
            if (Schema::hasColumn('join_requests', 'responded_by')) {
                $table->dropForeign(['responded_by']);
                $table->dropColumn('responded_by');
            }
            if (Schema::hasColumn('join_requests', 'responded_at')) {
                $table->dropColumn('responded_at');
            }
        });
    }
};
