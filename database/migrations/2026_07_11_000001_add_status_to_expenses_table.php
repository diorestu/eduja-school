<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'status')) {
                $table->string('status')->default('pending')->after('is_tax_paid');
                $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }

            if (Schema::hasColumn('expenses', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }

            if (Schema::hasColumn('expenses', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
