<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_accounts', function (Blueprint $table) {
            $table->decimal('opening_balance', 15, 2)->default(0)->after('account_number');
            $table->timestamp('last_reconciled_at')->nullable()->after('is_active');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('school_id')->constrained('school_accounts')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->after('reviewed_at');
            $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('id')->constrained('schools')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->after('invoice_id')->constrained('school_accounts')->nullOnDelete();
            $table->string('status')->default('approved')->after('recipient_name');
            $table->index(['school_id', 'status', 'payment_date']);
        });

        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('submitted_by')->constrained('school_accounts')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->after('review_note');
            $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('budget_plans', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        Schema::table('book_closings', function (Blueprint $table) {
            $table->json('validation_results')->nullable()->after('snapshot');
            $table->json('audit_metadata')->nullable()->after('validation_results');
        });

        Schema::create('finance_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('income_type_id')->nullable()->constrained('income_types')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('school_accounts')->nullOnDelete();
            $table->string('source_funding');
            $table->unsignedSmallInteger('bos_year')->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('received_date');
            $table->string('payment_method')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('status')->default('approved');
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status', 'received_date']);
        });

        Schema::create('fund_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('income_type_id')->constrained('income_types')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('school_accounts')->cascadeOnDelete();
            $table->string('name');
            $table->string('method')->default('nominal');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('budget_plan_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('budget_plan_id')->constrained('budget_plans')->cascadeOnDelete();
            $table->decimal('old_amount', 15, 2);
            $table->decimal('new_amount', 15, 2);
            $table->text('reason');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_plan_revisions');
        Schema::dropIfExists('fund_allocations');
        Schema::dropIfExists('finance_incomes');

        Schema::table('book_closings', function (Blueprint $table) {
            $table->dropColumn(['validation_results', 'audit_metadata']);
        });

        Schema::table('budget_plans', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['approved_by', 'approved_at']);
        });

        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropForeign(['posted_by']);
            $table->dropColumn(['account_id', 'posted_at', 'posted_by']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'status', 'payment_date']);
            $table->dropForeign(['school_id']);
            $table->dropForeign(['account_id']);
            $table->dropColumn(['school_id', 'account_id', 'status']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropForeign(['posted_by']);
            $table->dropColumn(['account_id', 'posted_at', 'posted_by']);
        });

        Schema::table('school_accounts', function (Blueprint $table) {
            $table->dropColumn(['opening_balance', 'last_reconciled_at']);
        });
    }
};
