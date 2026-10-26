<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('student_transfer_requests',function (Blueprint $t) {
            $t->id(); $t->foreignId('school_id')->constrained()->cascadeOnDelete(); $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('requested_by')->constrained('users')->cascadeOnDelete(); $t->string('destination'); $t->date('transfer_date'); $t->text('reason');
            $t->string('document_path'); $t->string('document_name'); $t->string('status')->default('pending'); $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('review_note')->nullable(); $t->timestamp('reviewed_at')->nullable(); $t->timestamps(); $t->index(['school_id','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('student_transfer_requests'); }
};
