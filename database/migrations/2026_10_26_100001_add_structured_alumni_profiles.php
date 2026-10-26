<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alumni', function (Blueprint $table) {
            $table->string('village', 150)->nullable();
            $table->string('city', 150)->nullable();
            $table->string('province', 150)->nullable();
        });
        Schema::create('alumni_education_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumni_id')->constrained('alumni')->cascadeOnDelete();
            $table->foreignId('source_school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->string('source', 20);
            $table->string('institution_name');
            $table->string('department')->nullable();
            $table->unsignedSmallInteger('entry_year')->nullable();
            $table->unsignedSmallInteger('graduation_year')->nullable();
            $table->timestamps();
            $table->unique(['alumni_id', 'source_school_id', 'source'], 'alumni_education_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni_education_histories');
        Schema::table('alumni', fn (Blueprint $table) => $table->dropColumn(['village', 'city', 'province']));
    }
};
