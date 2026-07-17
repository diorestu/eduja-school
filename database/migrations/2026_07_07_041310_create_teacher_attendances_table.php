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
        Schema::create('teacher_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->date('attendance_date');
            $table->string('status', 2); // H (Hadir), S (Sakit), I (Izin), A (Alfa), DL (Dinas Luar)
            $table->string('note')->nullable();
            $table->timestamps();

            // Prevent duplicate records for the same teacher on the same date
            $table->unique(['teacher_id', 'attendance_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_attendances');
    }
};
