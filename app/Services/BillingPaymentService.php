<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\SchoolAccount;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingPaymentService
{
    public function destinationAccount(int $schoolId, string $method, ?int $accountId, bool $required, bool $committeeTransfer = false): ?SchoolAccount
    {
        $query = SchoolAccount::where('school_id', $schoolId)->where('is_active', true);
        if ($committeeTransfer) {
            if ($method === 'Tunai') {
                $query->where(fn ($q) => $q->whereNull('source_funding')->orWhere('source_funding', 'komite'));
            } else {
                $query->whereRaw('LOWER(type) = ?', ['bank'])->where(fn ($q) => $q->whereNull('source_funding')->orWhere('source_funding', 'komite'));
            }
        }
        $account = $accountId
            ? $query->whereKey($accountId)->lockForUpdate()->first()
            : $query->whereIn(DB::raw('LOWER(type)'), $method === 'Tunai' ? ['tunai', 'cash'] : ['bank'])->orderBy('id')->lockForUpdate()->first();
        if (($required || $accountId) && ! $account) {
            throw ValidationException::withMessages(['account_id' => 'Pilih rekening sekolah yang aktif atau lengkapi rekening sesuai metode pembayaran.']);
        }

        return $account;
    }

    public static function cents(string|int|float|null $amount): int
    {
        $parts = explode('.', (string) ($amount ?? 0));

        return (int) $parts[0] * 100 + (int) str_pad(substr($parts[1] ?? '', 0, 2), 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public function remainingCents(Invoice $invoice): int
    {
        $paid = $invoice->transactions()->whereIn('status', ['approved', 'confirmed'])->get()->sum(fn ($tx) => self::cents($tx->amount_paid));

        return max(0, self::cents($invoice->total_amount) - $paid);
    }

    public function refreshCharges(Invoice $invoice, CarbonInterface $date): void
    {
        if (! $invoice->billing_period_id || $invoice->status === 'Lunas') {
            return;
        }
        DB::transaction(function () use ($invoice, $date) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'Lunas' || $this->remainingCents($locked) === 0) {
                return;
            }
            $days = max(0, (int) $locked->due_date->diffInDays($date->copy()->startOfDay(), false));
            $fee = min($days * self::cents($locked->late_fee_per_day), self::cents($locked->late_fee_maximum));
            // A backdated receipt cannot erase fees that have already accrued.
            $fee = max($fee, self::cents($locked->late_fee_amount));
            $locked->update(['late_fee_amount' => self::decimal($fee), 'total_amount' => self::decimal(self::cents($locked->principal_amount) + $fee)]);
            if ($fee > 0) {
                $locked->invoiceItems()->updateOrCreate(['is_late_fee' => true], ['name' => 'Denda keterlambatan', 'amount' => self::decimal($fee)]);
            }
            $invoice->setRawAttributes($locked->getAttributes(), true);
        });
    }

    public function previewCharges(Invoice $invoice, CarbonInterface $date): void
    {
        if (! $invoice->billing_period_id || $invoice->status === 'Lunas' || $invoice->remaining_amount <= 0) {
            return;
        }
        $days = max(0, (int) $invoice->due_date->diffInDays($date->copy()->startOfDay(), false));
        $fee = max(self::cents($invoice->late_fee_amount), min($days * self::cents($invoice->late_fee_per_day), self::cents($invoice->late_fee_maximum)));
        $invoice->forceFill(['late_fee_amount' => self::decimal($fee), 'total_amount' => self::decimal(self::cents($invoice->principal_amount) + $fee)]);
    }

    public function validatePayment(Invoice $invoice, string|int|float $amount): void
    {
        $cents = self::cents($amount);
        $remaining = $this->remainingCents($invoice);
        if ($cents <= 0 || $cents > $remaining) {
            throw ValidationException::withMessages(['amount_paid' => 'Pembayaran harus lebih dari nol dan tidak melebihi sisa tagihan.']);
        }
        if (! $invoice->billing_period_id) {
            return;
        }
        if (! $invoice->allow_installment && $cents !== $remaining) {
            throw ValidationException::withMessages(['amount_paid' => 'Tagihan ini harus dilunasi sekaligus.']);
        }
        if ($invoice->allow_installment && $cents < min($remaining, self::cents($invoice->minimum_installment))) {
            throw ValidationException::withMessages(['amount_paid' => 'Pembayaran kurang dari minimal cicilan. Sisa akhir boleh dilunasi seluruhnya.']);
        }
    }

    public function syncStatus(Invoice $invoice): void
    {
        $remaining = $this->remainingCents($invoice);
        $invoice->update(['status' => $remaining === 0 ? 'Lunas' : ($remaining < self::cents($invoice->total_amount) ? 'Cicilan' : 'Belum Lunas')]);
    }
}
