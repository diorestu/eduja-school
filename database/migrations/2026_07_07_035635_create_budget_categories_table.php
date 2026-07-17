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
        Schema::create('budget_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g. 01.01, 02.03, 03.01
            $table->string('name'); // e.g. Alat Tulis Kantor, Honorarium, Belanja Modal
            $table->string('source_funding')->default('BOS'); // BOS, Yayasan, Komite
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_categories');
    }
};
