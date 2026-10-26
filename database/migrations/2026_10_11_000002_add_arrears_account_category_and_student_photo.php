<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_accounts', fn (Blueprint $table) => $table->string('source_funding', 20)->nullable()->index());
        Schema::table('students', fn (Blueprint $table) => $table->string('photo_path')->nullable());
    }

    public function down(): void
    {
        Schema::table('school_accounts', fn (Blueprint $table) => $table->dropColumn('source_funding'));
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn('photo_path'));
    }
};
