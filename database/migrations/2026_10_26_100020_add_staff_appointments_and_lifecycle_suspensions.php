<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_user_roles', fn (Blueprint $table) => $table->string('lifecycle_suspended_by')->nullable());
        Schema::create('staff_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->string('role', 40);
            $table->string('appointment_number');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('appointing_institution');
            $table->string('document_path');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'teacher_id', 'ends_on']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('staff_appointments');
        Schema::table('school_user_roles', fn (Blueprint $table) => $table->dropColumn('lifecycle_suspended_by'));
    }
};
