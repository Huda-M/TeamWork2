<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('github_url')->nullable()->after('description');
            $table->string('category')->nullable()->after('github_url');
            $table->string('required_role')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['github_url', 'category', 'required_role']);
        });
    }
};
