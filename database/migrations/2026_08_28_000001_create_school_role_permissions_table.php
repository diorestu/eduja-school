<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('role');
            $table->string('permission');
            $table->boolean('is_allowed')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'role', 'permission'], 'school_role_permission_unique');
            $table->index(['school_id', 'role', 'is_allowed'], 'school_role_permission_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_role_permissions');
    }
};
