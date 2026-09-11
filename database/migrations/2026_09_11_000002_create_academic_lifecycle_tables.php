<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifecycle_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->date('effective_date')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('student_class_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('action');
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'student_id']);
        });

        Schema::table('alumni', function (Blueprint $table) {
            if (! Schema::hasColumn('alumni', 'department_name')) {
                $table->string('department_name')->nullable()->after('graduation_year');
            }
        });
    }

    public function down(): void
    {
        Schema::table('alumni', function (Blueprint $table) {
            if (Schema::hasColumn('alumni', 'department_name')) {
                $table->dropColumn('department_name');
            }
        });

        Schema::dropIfExists('student_class_histories');
        Schema::dropIfExists('lifecycle_histories');
    }
};
