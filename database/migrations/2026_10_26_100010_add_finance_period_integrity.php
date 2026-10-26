<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_closings', function (Blueprint $table) {
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('source_funding')->nullable();
            $table->foreignId('budget_year_id')->nullable()->constrained('budget_years')->restrictOnDelete();
        });
        Schema::table('budget_plans', function (Blueprint $table) {
            $table->foreignId('budget_category_id')->nullable()->constrained('budget_categories')->restrictOnDelete();
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('budget_plan_id')->nullable()->constrained('budget_plans')->restrictOnDelete();
            $table->unsignedSmallInteger('bos_year')->nullable();
        });
        Schema::create('finance_account_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_account_id')->constrained('school_accounts')->restrictOnDelete();
            $table->foreignId('to_account_id')->constrained('school_accounts')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('transfer_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('finance_wallet_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('virtual_wallet_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('effective_date');
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->timestamps();
            $table->index(['school_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_wallet_movements');
        Schema::dropIfExists('finance_account_transfers');
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_plan_id');
            $table->dropColumn('bos_year');
        });
        Schema::table('budget_plans', fn (Blueprint $table) => $table->dropConstrainedForeignId('budget_category_id'));
        Schema::table('book_closings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_year_id');
            $table->dropColumn(['period_start', 'period_end', 'source_funding']);
        });
    }
};
