<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_savings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->string('type'); // Setoran, Penarikan
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->string('reference_number')->unique(); // e.g. SAV/20260707/0001
            $table->string('note')->nullable(); // e.g. Uang saku titipan
            $table->string('recipient_name')->nullable(); // Operator/Wali Kelas who authorized
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_savings');
    }
};
