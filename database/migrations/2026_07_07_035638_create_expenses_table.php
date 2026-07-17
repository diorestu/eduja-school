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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_category_id')->nullable()->constrained('budget_categories')->onDelete('set null');
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->string('expense_name'); // e.g. Pembelian Kertas A4, Honor GTT Bulan Juli
            $table->decimal('amount', 15, 2);
            $table->date('transaction_date');
            $table->string('source_funding')->default('BOS'); // BOS, Yayasan, Komite
            $table->string('payment_method')->default('Tunai'); // Tunai, Transfer
            $table->string('reference_invoice')->nullable(); // Invoice/Nota Number
            $table->string('recipient_name')->nullable(); // Penerima dana (Toko/Guru/Staf)
            
            // Tax alignment (Pajak BOS/BKU)
            $table->string('tax_type')->nullable(); // PPN, PPh 21, PPh 22, PPh 23
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->boolean('is_tax_paid')->default(false); // Whether the tax has been deposited (disetor) to the state
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
