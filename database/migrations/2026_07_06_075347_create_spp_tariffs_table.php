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
        Schema::create('spp_tariffs', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. 'SPP Bulanan X-A', 'Uang Pangkal X'
            $table->decimal('amount', 12, 2);
            $table->enum('type', ['Bulanan', 'Sekali'])->default('Bulanan'); // Bulanan (recurring) or Sekali (one-off)
            $table->foreignId('school_class_id')->nullable()->constrained('school_classes')->onDelete('cascade'); // Null means applicable to all classes
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spp_tariffs');
    }
};
