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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->decimal('amount_paid', 12, 2);
            $table->date('payment_date');
            $table->string('payment_method')->default('Tunai'); // e.g. Tunai, Transfer Bank, E-Wallet
            $table->string('receipt_number')->unique(); // e.g. RCP/202607/0001
            $table->string('recipient_name')->nullable(); // Cashier / system username
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
