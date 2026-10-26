<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('learning_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('learning_materials'); }
};
