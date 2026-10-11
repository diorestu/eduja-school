<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedSmallInteger('generation')->nullable()->index();
        });
        Schema::table('billing_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('billing_day')->nullable();
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->date('recurrence_start')->nullable();
        });
        Schema::create('billing_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_item_id')->constrained()->restrictOnDelete();
            $table->string('period_key', 10);
            $table->date('start_date');
            $table->date('due_date');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['billing_item_id', 'period_key']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('billing_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('billing_period_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('principal_amount', 15, 2)->nullable();
            $table->decimal('late_fee_amount', 15, 2)->default(0);
            $table->boolean('allow_installment')->default(false);
            $table->decimal('minimum_installment', 15, 2)->nullable();
            $table->decimal('late_fee_per_day', 15, 2)->default(0);
            $table->decimal('late_fee_maximum', 15, 2)->default(0);
            $table->unique(['billing_period_id', 'student_id']);
        });
        Schema::table('invoice_items', fn (Blueprint $table) => $table->boolean('is_late_fee')->default(false));
        Schema::create('billing_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->date('scheduled_date');
            $table->json('invoice_ids');
            $table->string('recipient')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('message')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('provider_reference')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'student_id', 'scheduled_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_reminders');
        Schema::table('invoice_items', fn (Blueprint $table) => $table->dropColumn('is_late_fee'));
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['billing_period_id', 'student_id']);
            $table->dropConstrainedForeignId('billing_period_id');
            $table->dropConstrainedForeignId('billing_item_id');
            $table->dropColumn(['principal_amount', 'late_fee_amount', 'allow_installment', 'minimum_installment', 'late_fee_per_day', 'late_fee_maximum']);
        });
        Schema::dropIfExists('billing_periods');
        Schema::table('billing_items', fn (Blueprint $table) => $table->dropColumn(['billing_day', 'due_day', 'recurrence_start']));
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn('generation'));
    }
};
