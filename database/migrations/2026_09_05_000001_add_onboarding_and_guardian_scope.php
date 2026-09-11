<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('registration_type')->nullable()->after('role');
            $table->string('onboarding_status')->default('active')->after('registration_type');
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->string('status')->default('active')->after('is_active');
            $table->string('registration_code')->nullable()->unique()->after('status');
        });

        Schema::table('school_user_roles', function (Blueprint $table) {
            $table->string('membership_status')->default('active')->after('is_active');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('guardian_user_id')->nullable()->after('school_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['guardian_user_id']);
            $table->dropColumn('guardian_user_id');
        });
        Schema::table('school_user_roles', fn (Blueprint $table) => $table->dropColumn('membership_status'));
        Schema::table('schools', function (Blueprint $table) {
            $table->dropUnique(['registration_code']);
            $table->dropColumn(['status', 'registration_code']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['registration_type', 'onboarding_status']));
    }
};
