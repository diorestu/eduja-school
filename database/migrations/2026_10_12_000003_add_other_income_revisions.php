<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_incomes', function (Blueprint $table) {
            $table->string('income_category')->nullable()->index();
            $table->json('other_allocations')->nullable();
            $table->uuid('other_submission_key')->nullable();
            $table->string('other_fingerprint', 64)->nullable();
            $table->unique(['school_id', 'other_submission_key']);
        });
        Schema::create('other_income_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('finance_income_id')->constrained()->restrictOnDelete();
            $table->json('old_data');
            $table->json('new_data');
            $table->string('fingerprint', 64);
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->index(['finance_income_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('other_income_revisions');
        Schema::table('finance_incomes', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'other_submission_key']);
            $table->dropIndex(['income_category']);
            $table->dropColumn(['income_category', 'other_allocations', 'other_submission_key', 'other_fingerprint']);
        });
    }
};
