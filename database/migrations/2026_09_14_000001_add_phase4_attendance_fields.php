<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_attendances', function (Blueprint $table): void {
            if (! Schema::hasColumn('student_attendances', 'request_id')) {
                $table->foreignId('request_id')->nullable()->after('note')->constrained('attendance_requests')->nullOnDelete();
            }
            if (! Schema::hasColumn('student_attendances', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('request_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('student_attendances', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
        });

        Schema::table('teacher_attendances', function (Blueprint $table): void {
            if (! Schema::hasColumn('teacher_attendances', 'request_id')) {
                $table->foreignId('request_id')->nullable()->after('note')->constrained('attendance_requests')->nullOnDelete();
            }
            if (! Schema::hasColumn('teacher_attendances', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('request_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('teacher_attendances', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
        });

        Schema::table('attendance_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('attendance_requests', 'requester_id')) {
                $table->foreignId('requester_id')->nullable()->after('submitted_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('attendance_requests', 'subject_type')) {
                $table->string('subject_type')->nullable()->after('teacher_id');
            }
            if (! Schema::hasColumn('attendance_requests', 'subject_id')) {
                $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            }
            if (! Schema::hasColumn('attendance_requests', 'document_name')) {
                $table->string('document_name')->nullable()->after('document_path');
            }
            if (! Schema::hasColumn('attendance_requests', 'document_mime')) {
                $table->string('document_mime')->nullable()->after('document_name');
            }
            if (! Schema::hasColumn('attendance_requests', 'approval_request_id')) {
                $table->foreignId('approval_request_id')->nullable()->after('status')->constrained('approval_requests')->nullOnDelete();
            }
            if (! Schema::hasColumn('attendance_requests', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('approval_request_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('attendance_requests', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
            if (! Schema::hasColumn('attendance_requests', 'review_note')) {
                $table->text('review_note')->nullable()->after('reviewed_at');
            }
        });

        Schema::table('attendance_requests', function (Blueprint $table): void {
            $table->index(['subject_type', 'subject_id']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_requests', function (Blueprint $table): void {
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropIndex(['school_id', 'status']);
            $table->dropForeign(['reviewed_by']);
            $table->dropForeign(['approval_request_id']);
            $table->dropForeign(['requester_id']);
            $table->dropColumn([
                'requester_id', 'subject_type', 'subject_id', 'document_name', 'document_mime',
                'approval_request_id', 'reviewed_by', 'reviewed_at', 'review_note',
            ]);
        });

        foreach (['student_attendances', 'teacher_attendances'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropForeign(['reviewed_by']);
                $table->dropForeign(['request_id']);
                $table->dropColumn(['request_id', 'reviewed_by', 'reviewed_at']);
            });
        }
    }
};
