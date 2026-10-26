<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('attendance_requests',function (Blueprint $table) { $table->string('leave_type')->nullable(); $table->string('contact_phone',40)->nullable(); });
        Schema::create('teacher_leave_entitlements',function (Blueprint $table) {
            $table->id(); $table->foreignId('school_id')->constrained()->cascadeOnDelete(); $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year'); $table->unsignedSmallInteger('days'); $table->timestamps(); $table->unique(['school_id','teacher_id','year']);
        });
    }
    public function down(): void { Schema::dropIfExists('teacher_leave_entitlements'); Schema::table('attendance_requests',fn (Blueprint $t)=>$t->dropColumn(['leave_type','contact_phone'])); }
};
