<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained('school_accounts')->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->string('receipt_number')->nullable()->unique();
            $table->uuid('submission_key');
            $table->string('fingerprint', 64);
            $table->decimal('amount_paid', 15, 2);
            $table->date('payment_date');
            $table->string('payment_method');
            $table->timestamps();
            $table->unique(['school_id', 'submission_key']);
        });
        Schema::table('transactions', fn (Blueprint $table) => $table->foreignId('committee_receipt_id')->nullable()->constrained()->restrictOnDelete());
    }

    public function down(): void
    {
        Schema::table('transactions', fn (Blueprint $table) => $table->dropConstrainedForeignId('committee_receipt_id'));
        Schema::dropIfExists('committee_receipts');
    }
};
