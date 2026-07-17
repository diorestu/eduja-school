<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('npsn')->nullable()->unique();
            $table->string('level')->default('sma');
            $table->string('ownership')->default('swasta');
            $table->string('foundation_name')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('school_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['school_id', 'user_id', 'role']);
        });

        foreach ([
            'academic_years',
            'teachers',
            'students',
            'school_classes',
            'spp_tariffs',
            'invoices',
            'budget_categories',
            'expenses',
            'student_savings',
            'student_attendances',
            'teacher_attendances',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'school_id')) {
                    $table->foreignId('school_id')->nullable()->after('id')->constrained('schools')->nullOnDelete();
                }
            });
        }

        Schema::table('academic_years', function (Blueprint $table) {
            if (! Schema::hasColumn('academic_years', 'start_date')) {
                $table->date('start_date')->nullable()->after('semester');
                $table->date('end_date')->nullable()->after('start_date');
            }
        });

        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'status')) {
                $table->string('status')->default('active')->after('is_active');
                $table->date('status_date')->nullable()->after('status');
                $table->string('status_note')->nullable()->after('status_date');
            }
        });

        Schema::table('teachers', function (Blueprint $table) {
            if (! Schema::hasColumn('teachers', 'status')) {
                $table->string('status')->default('active')->after('is_active');
                $table->date('status_date')->nullable()->after('status');
                $table->string('status_note')->nullable()->after('status_date');
            }
        });

        Schema::table('student_attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('student_attendances', 'clock_in_at')) {
                $table->time('clock_in_at')->nullable()->after('attendance_date');
                $table->time('clock_out_at')->nullable()->after('clock_in_at');
                $table->string('source')->default('manual')->after('clock_out_at');
                $table->string('rfid_uid')->nullable()->after('source');
                $table->decimal('latitude', 10, 7)->nullable()->after('rfid_uid');
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                $table->string('photo_path')->nullable()->after('longitude');
                $table->string('sync_status')->default('synced')->after('photo_path');
            }
        });

        Schema::table('teacher_attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('teacher_attendances', 'clock_in_at')) {
                $table->time('clock_in_at')->nullable()->after('attendance_date');
                $table->time('clock_out_at')->nullable()->after('clock_in_at');
                $table->string('source')->default('manual')->after('clock_out_at');
                $table->string('rfid_uid')->nullable()->after('source');
                $table->decimal('latitude', 10, 7)->nullable()->after('rfid_uid');
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                $table->string('photo_path')->nullable()->after('longitude');
                $table->string('sync_status')->default('synced')->after('photo_path');
            }
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['school_id', 'code']);
        });

        Schema::table('school_classes', function (Blueprint $table) {
            if (! Schema::hasColumn('school_classes', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('grade')->constrained('departments')->nullOnDelete();
            }
        });

        Schema::create('alumni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('name');
            $table->string('nisn')->nullable();
            $table->year('graduation_year')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('current_status')->nullable();
            $table->text('education_history')->nullable();
            $table->string('current_job')->nullable();
            $table->timestamps();
        });

        Schema::create('school_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('Tunai');
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('income_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('category')->default('komite');
            $table->boolean('uses_allocation')->default(false);
            $table->boolean('requires_approval')->default(false);
            $table->timestamps();
            $table->unique(['school_id', 'code']);
        });

        Schema::create('expense_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('source_funding')->default('komite');
            $table->string('bos_component')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->string('supporting_document_path')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'code']);
        });

        Schema::create('budget_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('budget_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('budget_year_id')->nullable()->constrained('budget_years')->nullOnDelete();
            $table->string('source_funding')->default('komite');
            $table->string('program_name');
            $table->string('activity_name');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->default('pending');
            $table->text('revision_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id')->nullable();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->text('note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['approvable_type', 'approvable_id']);
        });

        Schema::create('billing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('income_type_id')->nullable()->constrained('income_types')->nullOnDelete();
            $table->string('name');
            $table->decimal('amount', 15, 2);
            $table->boolean('allow_installment')->default(false);
            $table->decimal('minimum_installment', 15, 2)->nullable();
            $table->boolean('has_late_fee')->default(false);
            $table->decimal('late_fee_per_day', 15, 2)->default(0);
            $table->decimal('late_fee_maximum', 15, 2)->default(0);
            $table->string('billing_frequency')->default('Bulanan');
            $table->date('due_date')->nullable();
            $table->string('target_type')->default('school');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('payment_method')->default('Transfer Bank');
            $table->string('proof_path')->nullable();
            $table->string('status')->default('pending');
            $table->text('review_note')->nullable();
            $table->timestamps();
        });

        Schema::create('book_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('monthly');
            $table->string('period');
            $table->string('status')->default('closed');
            $table->json('snapshot')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'type', 'period']);
        });

        Schema::create('attendance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('request_type');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('reason')->nullable();
            $table->string('document_path')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('category')->default('umum');
            $table->text('body');
            $table->string('target_type')->default('school');
            $table->string('attachment_path')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['announcement_id', 'user_id']);
        });

        Schema::create('ai_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('prompt');
            $table->longText('response')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'ai_materials',
            'announcement_reads',
            'announcements',
            'attendance_requests',
            'book_closings',
            'payment_submissions',
            'billing_items',
            'approval_requests',
            'budget_plans',
            'budget_years',
            'expense_types',
            'income_types',
            'school_accounts',
            'alumni',
            'departments',
            'school_user_roles',
        ] as $tableName) {
            Schema::dropIfExists($tableName);
        }

        Schema::dropIfExists('schools');
    }
};
