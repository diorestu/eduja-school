<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) $table->string('phone')->nullable()->after('email');
            if (! Schema::hasColumn('users', 'phone_normalized')) $table->string('phone_normalized')->nullable()->unique()->after('phone');
        });
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'user_id')) $table->foreignId('user_id')->nullable()->after('school_id')->constrained('users')->nullOnDelete();
        });
        Schema::table('teachers', function (Blueprint $table) {
            if (! Schema::hasColumn('teachers', 'user_id')) $table->foreignId('user_id')->nullable()->after('school_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) { if (Schema::hasColumn('teachers', 'user_id')) { $table->dropForeign(['user_id']); $table->dropColumn('user_id'); } });
        Schema::table('students', function (Blueprint $table) { if (Schema::hasColumn('students', 'user_id')) { $table->dropForeign(['user_id']); $table->dropColumn('user_id'); } });
        Schema::table('users', function (Blueprint $table) { if (Schema::hasColumn('users', 'phone_normalized')) { $table->dropUnique(['phone_normalized']); $table->dropColumn('phone_normalized'); } if (Schema::hasColumn('users', 'phone')) $table->dropColumn('phone'); });
    }
};
