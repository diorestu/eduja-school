<?php

namespace App\Services;

use App\Models\BillingItem;
use App\Models\FundAllocation;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\VirtualWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceWalletAllocationService
{
    public function allocate(Invoice $invoice, Transaction $receipt): void
    {
        $bill = $invoice->billing_item_id ? BillingItem::where('school_id', $invoice->school_id)->find($invoice->billing_item_id) : null;
        if (! $bill && ! $invoice->billing_item_id) {
            $name = $invoice->invoiceItems()->where('is_late_fee', false)->orderBy('id')->first()?->name;
            $matches = BillingItem::where('school_id', $invoice->school_id)->where('name', $name)->get();
            $bill = $matches->count() === 1 ? $matches->first() : null;
        }
        if (! $bill?->income_type_id) {
            return;
        }
        $amount = BillingPaymentService::cents($receipt->amount_paid);
        $allocations = FundAllocation::where('school_id', $invoice->school_id)->where('income_type_id', $bill->income_type_id)
            ->where('status', 'active')->whereNotNull('virtual_wallet_id')->orderBy('virtual_wallet_id')->get();
        $updates = [];
        foreach ($allocations as $allocation) {
            $wallet = VirtualWallet::where('school_id', $invoice->school_id)->whereIn(DB::raw('LOWER(status)'), ['active', 'aktif'])->whereKey($allocation->virtual_wallet_id)->lockForUpdate()->first();
            if (! $wallet) {
                continue;
            }
            $value = BillingPaymentService::cents($allocation->amount);
            $percentage = in_array(strtolower($allocation->method), ['percentage', 'persentase', 'percent', '%'], true);
            if ($value < 0 || ($percentage && $value > 10000)) {
                throw ValidationException::withMessages(['amount_paid' => 'Konfigurasi alokasi dana tidak valid.']);
            }
            $cents = $percentage
                ? intdiv($amount * $value + 5000, 10000) : min($amount, $value);
            if ($cents < 0) {
                throw ValidationException::withMessages(['amount_paid' => 'Konfigurasi alokasi dana tidak valid.']);
            }
            $updates[] = [$wallet, $cents];
        }
        if (array_sum(array_column($updates, 1)) > $amount) {
            throw ValidationException::withMessages(['amount_paid' => 'Total alokasi dompet melebihi pembayaran. Bendahara perlu memperbaiki aturan alokasi.']);
        }
        foreach ($updates as [$wallet, $cents]) {
            app(FinanceWalletService::class)->adjust($wallet, $cents, $receipt->payment_date, $receipt);
        }
    }
}
