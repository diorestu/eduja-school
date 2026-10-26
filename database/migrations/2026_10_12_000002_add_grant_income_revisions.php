<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_incomes', function (Blueprint $table) {
            $table->json('grant_allocations')->nullable();
            $table->uuid('grant_submission_key')->nullable();
            $table->string('grant_fingerprint', 64)->nullable();
            $table->unique(['school_id', 'grant_submission_key']);
        });
        Schema::create('grant_income_revisions', function (Blueprint $table) {
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
        Schema::dropIfExists('grant_income_revisions');
        Schema::table('finance_incomes', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'grant_submission_key']);
            $table->dropColumn(['grant_allocations', 'grant_submission_key', 'grant_fingerprint']);
        });
    }
};
