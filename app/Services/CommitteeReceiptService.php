<?php

namespace App\Services;

use App\Models\CommitteeReceipt;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommitteeReceiptService
{
    public function __construct(private BillingPaymentService $payments, private InvoiceWalletAllocationService $allocations) {}

    public function receive(int $schoolId, Student $student, int $actorId, array $attributes): CommitteeReceipt
    {
        abort_unless((int) $student->school_id === $schoolId, 404);
        $lines = collect($attributes['payments'])->map(fn ($line) => ['invoice_id' => (int) $line['invoice_id'], 'amount_paid' => BillingPaymentService::decimal(BillingPaymentService::cents($line['amount_paid']))])->sortBy('invoice_id')->values();
        $fingerprint = hash('sha256', json_encode([$student->id, $actorId, $lines->all(), $attributes['payment_method'], $attributes['payment_date'], isset($attributes['account_id']) ? (int) $attributes['account_id'] : null]));

        return DB::transaction(function () use ($schoolId, $student, $actorId, $attributes, $lines, $fingerprint) {
            $existing = $this->existing($schoolId, $attributes['submission_key'], $fingerprint);
            if ($existing) {
                return $existing;
            }
            $invoices = Invoice::ownedBySchool($schoolId)->committeeOnly($schoolId)->where('student_id', $student->id)
                ->whereIn('id', $lines->pluck('invoice_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            // A concurrent identical request may have finished while invoice locks were acquired.
            $existing = $this->existing($schoolId, $attributes['submission_key'], $fingerprint, true);
            if ($existing) {
                return $existing;
            }
            if ($invoices->count() !== $lines->count()) {
                throw ValidationException::withMessages(['payments' => 'Seluruh tagihan harus termasuk Komite dan milik siswa serta sekolah yang dipilih.']);
            }
            $total = 0;
            foreach ($lines as $line) {
                $invoice = $invoices[$line['invoice_id']];
                $this->payments->refreshCharges($invoice, now());
                try {
                    $this->payments->validatePayment($invoice, $line['amount_paid']);
                } catch (ValidationException $error) {
                    throw ValidationException::withMessages(['payments' => $invoice->invoice_number.': '.collect($error->errors())->flatten()->first()]);
                }
                $total += BillingPaymentService::cents($line['amount_paid']);
            }
            $account = $this->payments->destinationAccount($schoolId, $attributes['payment_method'], isset($attributes['account_id']) ? (int) $attributes['account_id'] : null, true, true);
            $receipt = CommitteeReceipt::create(['school_id' => $schoolId, 'student_id' => $student->id, 'account_id' => $account->id, 'received_by' => $actorId,
                'submission_key' => $attributes['submission_key'], 'fingerprint' => $fingerprint, 'amount_paid' => BillingPaymentService::decimal($total),
                'payment_date' => $attributes['payment_date'], 'payment_method' => $attributes['payment_method']]);
            $receipt->update(['receipt_number' => 'PK'.str_replace('-', '', $attributes['payment_date']).str_pad((string) $receipt->id, 6, '0', STR_PAD_LEFT)]);
            foreach ($lines as $index => $line) {
                $invoice = $invoices[$line['invoice_id']];
                if (! $invoice->school_id) {
                    $invoice->update(['school_id' => $schoolId]);
                }
                $transaction = Transaction::create(['invoice_id' => $invoice->id, 'school_id' => $schoolId, 'committee_receipt_id' => $receipt->id,
                    'account_id' => $account->id, 'amount_paid' => $line['amount_paid'], 'payment_date' => $attributes['payment_date'],
                    'payment_method' => $attributes['payment_method'], 'receipt_number' => $receipt->receipt_number.'-'.($index + 1),
                    'recipient_name' => auth()->user()?->name, 'status' => 'approved']);
                $this->payments->syncStatus($invoice);
                $this->allocations->allocate($invoice, $transaction);
            }
            $account->increment('current_balance', BillingPaymentService::decimal($total));
            $phone = $student->parent_phone ?? $student->guardianUser?->phone;
            if ($phone) {
                $message = 'Pembayaran Komite '.$student->name.' tercatat. Nomor '.$receipt->receipt_number.', total Rp '.number_format($total / 100, $total % 100 === 0 ? 0 : 2, ',', '.').', mencakup '.$lines->count().' tagihan.';
                DB::afterCommit(fn () => app(NotificationService::class)->send('whatsapp', $phone, $message));
            }

            return $receipt;
        }, 3);
    }

    private function existing(int $schoolId, string $key, string $fingerprint, bool $lock = false): ?CommitteeReceipt
    {
        $receipt = CommitteeReceipt::where('school_id', $schoolId)->where('submission_key', $key)->when($lock, fn ($query) => $query->lockForUpdate())->first();
        if ($receipt && ! hash_equals($receipt->fingerprint, $fingerprint)) {
            throw ValidationException::withMessages(['submission_key' => 'Penerimaan ini sudah tersimpan dengan data berbeda. Muat ulang form sebelum membuat penerimaan baru.']);
        }

        return $receipt;
    }
}
