<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alumni', function (Blueprint $table) {
            if (! Schema::hasColumn('alumni', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('student_id')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('announcements', function (Blueprint $table) {
            if (! Schema::hasColumn('announcements', 'target_id')) {
                $table->unsignedBigInteger('target_id')->nullable()->after('target_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            if (Schema::hasColumn('announcements', 'target_id')) {
                $table->dropColumn('target_id');
            }
        });

        Schema::table('alumni', function (Blueprint $table) {
            if (Schema::hasColumn('alumni', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
        });
    }
};
