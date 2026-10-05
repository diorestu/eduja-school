<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('nominal', 15, 2)->default(0);
            $table->string('status')->default('active'); // active / inactive
            $table->string('source')->nullable(); // Asal alokasi dana
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
        });

        Schema::table('fund_allocations', function (Blueprint $table) {
            $table->foreignId('virtual_wallet_id')->nullable()->after('income_type_id')->constrained('virtual_wallets')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('fund_allocations', function (Blueprint $table) {
            $table->dropForeign(['virtual_wallet_id']);
            $table->dropColumn('virtual_wallet_id');
        });

        Schema::dropIfExists('virtual_wallets');
    }
};
