<?php

namespace App\Services;

use App\Contracts\BillingWhatsAppGateway;
use App\Models\BillingReminder;
use App\Models\Invoice;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class BillingReminderService
{
    public function __construct(private BillingWhatsAppGateway $gateway, private BillingPaymentService $payments, private BillingService $billing) {}

    public function prepare(CarbonInterface $date): void
    {
        $invoices = Invoice::with('billingItem')->whereNotNull('billing_period_id')->where('status', '!=', 'Lunas')->get();
        foreach ($invoices->groupBy(fn ($invoice) => $invoice->school_id.':'.$invoice->student_id) as $group) {
            $trigger = $group->contains(function ($invoice) use ($date) {
                $due = $invoice->due_date;
                $arrearsDay = $this->billing->onDay($date, (int) ($invoice->billingItem?->due_day ?: $due->day));

                return $due->isSameDay($date) || $due->copy()->subDays(7)->isSameDay($date)
                    || ($due->lt($date->copy()->startOfDay()) && $arrearsDay->isSameDay($date));
            });
            if (! $trigger) {
                continue;
            }
            $first = $group->first();
            DB::transaction(function () use ($first, $group, $date) {
                // Serialise each student's reminder creation across concurrent scheduler runs.
                Student::whereKey($first->student_id)->lockForUpdate()->firstOrFail();
                $reminder = BillingReminder::where('school_id', $first->school_id)->where('student_id', $first->student_id)->whereDate('scheduled_date', $date->toDateString())->first()
                    ?? BillingReminder::create(['school_id' => $first->school_id, 'student_id' => $first->student_id, 'scheduled_date' => $date->toDateString(), 'invoice_ids' => [], 'status' => 'pending', 'attempts' => 0]);
                if (in_array($reminder->status, ['pending', 'unconfigured', 'missing_recipient', 'failed'], true)) {
                    $ids = $group->filter(fn ($inv) => $inv->due_date->lte($date->copy()->addDays(7)->endOfDay()))->pluck('id')->all();
                    $reminder->update(['invoice_ids' => array_values(array_unique([...$reminder->invoice_ids, ...$ids]))]);
                }
            });
        }
    }

    public function deliverPending(): void
    {
        BillingReminder::whereIn('status', ['pending', 'unconfigured', 'missing_recipient', 'failed'])
            ->where('attempts', '<', 3)->whereDate('scheduled_date', '<=', now()->toDateString())
            ->chunkById(100, function ($reminders) {
                foreach ($reminders as $reminder) {
                    $this->deliver($reminder);
                }
            });
    }

    private function deliver(BillingReminder $record): void
    {
        DB::transaction(function () use ($record) {
            $reminder = BillingReminder::whereKey($record->id)->lockForUpdate()->firstOrFail();
            if (! in_array($reminder->status, ['pending', 'unconfigured', 'missing_recipient', 'failed'], true)) {
                return;
            }
            $invoices = Invoice::with('invoiceItems')->where('school_id', $reminder->school_id)->where('student_id', $reminder->student_id)
                ->whereIn('id', $reminder->invoice_ids)->whereNotNull('billing_period_id')->where('status', '!=', 'Lunas')->orderBy('id')->lockForUpdate()->get();
            foreach ($invoices as $invoice) {
                $this->payments->refreshCharges($invoice, now());
            }
            $invoices = $invoices->filter(fn ($inv) => $this->payments->remainingCents($inv) > 0);
            if ($invoices->isEmpty()) {
                $reminder->update(['status' => 'cancelled', 'failure_reason' => null]);

                return;
            }
            $student = Student::where('school_id', $reminder->school_id)->findOrFail($reminder->student_id);
            $recipient = preg_replace('/\D/', '', $student->parent_phone ?? $student->guardianUser?->phone ?? '');
            if (str_starts_with($recipient, '0')) {
                $recipient = '62'.substr($recipient, 1);
            }
            if (! preg_match('/^[1-9][0-9]{7,14}$/', $recipient)) {
                $reminder->update(['status' => 'missing_recipient', 'recipient' => null, 'failure_reason' => 'Nomor WhatsApp orang tua/wali belum valid.']);

                return;
            }
            if (! $this->gateway->configured()) {
                $reminder->update(['status' => 'unconfigured', 'recipient' => $recipient, 'failure_reason' => 'Provider WhatsApp belum terkonfigurasi.']);

                return;
            }
            $money = fn ($value) => 'Rp '.number_format((float) $value, 2, ',', '.');
            $message = 'Yth. Orang Tua/Wali '.$student->name.",\nBerikut kewajiban komite yang belum lunas:\n";
            foreach ($invoices as $inv) {
                $message .= $inv->invoice_number.' - '.($inv->invoiceItems->first()?->name ?? 'Komite').' (deadline '.$inv->due_date->format('d/m/Y').')'
                    .' | Pokok '.$money($inv->principal_amount).' | Denda '.$money($inv->late_fee_amount)
                    .' | Dibayar '.$money($inv->paid_amount).' | Sisa '.$money($inv->remaining_amount)."\n";
            }
            $message .= 'Total sisa: '.$money($invoices->sum('remaining_amount')).'. Mohon melakukan pembayaran melalui sekolah.';
            $reminder->update(['status' => 'processing', 'recipient' => $recipient, 'message' => $message, 'attempts' => $reminder->attempts + 1]);
            try {
                $reference = $this->gateway->send($recipient, $message, 'eduja-billing-'.$reminder->id);
                $reminder->update(['status' => 'sent', 'sent_at' => now(), 'provider_reference' => $reference, 'failure_reason' => null]);
            } catch (\DomainException) {
                $reminder->update(['status' => 'failed', 'failure_reason' => 'Provider menolak pengiriman. Periksa konfigurasi sebelum mencoba kembali.']);
            } catch (\Throwable) {
                $reminder->update(['status' => 'uncertain', 'failure_reason' => 'Hasil pengiriman belum diketahui. Rekonsiliasi dengan provider diperlukan sebelum mengirim ulang.']);
            }
        });
    }
}
