<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_items', function (Blueprint $table) {
            if (!Schema::hasColumn('billing_items', 'academic_year_id')) {
                $table->foreignId('academic_year_id')->nullable()->after('school_id')->constrained('academic_years')->nullOnDelete();
            }
            if (!Schema::hasColumn('billing_items', 'start_date')) {
                $table->date('start_date')->nullable()->after('billing_frequency');
            }
            if (!Schema::hasColumn('billing_items', 'target_department_id')) {
                $table->unsignedBigInteger('target_department_id')->nullable()->after('target_id');
            }
            if (!Schema::hasColumn('billing_items', 'target_generation')) {
                $table->string('target_generation', 20)->nullable()->after('target_department_id');
            }
            if (!Schema::hasColumn('billing_items', 'target_class_id')) {
                $table->unsignedBigInteger('target_class_id')->nullable()->after('target_generation');
            }
            if (!Schema::hasColumn('billing_items', 'target_student_id')) {
                $table->unsignedBigInteger('target_student_id')->nullable()->after('target_class_id');
            }
        });

        Schema::table('finance_incomes', function (Blueprint $table) {
            if (!Schema::hasColumn('finance_incomes', 'donor_name')) {
                $table->string('donor_name')->nullable()->after('source_funding');
            }
            if (!Schema::hasColumn('finance_incomes', 'notes')) {
                $table->text('notes')->nullable()->after('proof_path');
            }
        });

        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'expense_type_id')) {
                $table->foreignId('expense_type_id')->nullable()->after('budget_category_id')->constrained('expense_types')->nullOnDelete();
            }
            if (!Schema::hasColumn('expenses', 'virtual_wallet_id')) {
                $table->foreignId('virtual_wallet_id')->nullable()->after('expense_type_id')->constrained('virtual_wallets')->nullOnDelete();
            }
            if (!Schema::hasColumn('expenses', 'proof_path')) {
                $table->string('proof_path')->nullable()->after('reference_invoice');
            }
            if (!Schema::hasColumn('expenses', 'document_checklist')) {
                $table->json('document_checklist')->nullable()->after('proof_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('billing_items', function (Blueprint $table) {
            $table->dropColumn([
                'academic_year_id',
                'start_date',
                'target_department_id',
                'target_generation',
                'target_class_id',
                'target_student_id',
            ]);
        });

        Schema::table('finance_incomes', function (Blueprint $table) {
            $table->dropColumn(['donor_name', 'notes']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn([
                'expense_type_id',
                'virtual_wallet_id',
                'proof_path',
                'document_checklist',
            ]);
        });
    }
};
