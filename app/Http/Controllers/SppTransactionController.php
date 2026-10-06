<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Transaction;
use App\Models\Student;
use App\Models\SppTariff;
use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\FinanceIncome;
use App\Models\FundAllocation;
use App\Models\IncomeType;
use App\Models\BillingItem;
use App\Models\SchoolAccount;
use App\Models\VirtualWallet;
use App\Services\NotificationService;
use App\Services\SchoolContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class SppTransactionController extends Controller
{
    public function index(Request $request, SchoolContext $schoolContext)
    {
        $schoolId = $schoolContext->activeSchoolId();

        $invoices = Invoice::with(['student.schoolClasses', 'transactions', 'invoiceItems'])
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->orderBy('created_at', 'desc');

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $invoices->whereHas('student', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        $invoices = $invoices->get();
        $studentsQuery = Student::where('is_active', true);
        if ($schoolId) {
            $studentsQuery->where('school_id', $schoolId);
        }
        $students = $studentsQuery->orderBy('name', 'asc')->get();
        $activeYear = AcademicYear::where('is_active', true)
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->first();

        // Calculate student arrears for Tunggakan Siswa tab
        $arrearsStudents = $students->map(function ($student) use ($invoices) {
            $studentInvoices = $invoices->where('student_id', $student->id);
            $totalRemaining = $studentInvoices->sum(fn ($inv) => $inv->remaining_amount);
            $unpaidInvoices = $studentInvoices->filter(fn ($inv) => $inv->remaining_amount > 0)->values();

            $status = 'lunas';
            if ($totalRemaining > 0) {
                $hasOverdue = $unpaidInvoices->contains(fn ($inv) => $inv->due_date && $inv->due_date < now());
                $hasPaidSome = $unpaidInvoices->contains(fn ($inv) => $inv->paid_amount > 0);
                if ($hasOverdue) {
                    $status = 'menunggak';
                } elseif ($hasPaidSome) {
                    $status = 'cicil';
                } else {
                    $status = 'belum bayar';
                }
            }

            return [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis ?? '-',
                'nisn' => $student->nisn ?? '-',
                'class_name' => $student->schoolClasses->first()?->name ?? 'Kelas X',
                'total_arrears' => (float) $totalRemaining,
                'status' => $status,
                'unpaid_count' => $unpaidInvoices->count(),
                'invoices' => $unpaidInvoices->map(fn ($inv) => [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'item_name' => $inv->invoiceItems->first()?->name ?? 'Tagihan Komite',
                    'total_amount' => (float) $inv->total_amount,
                    'paid_amount' => (float) $inv->paid_amount,
                    'remaining_amount' => (float) $inv->remaining_amount,
                    'due_date' => $inv->due_date ? $inv->due_date->format('d M Y') : '-',
                ]),
            ];
        })->filter(fn ($s) => $s['total_arrears'] > 0 || $s['invoices']->isNotEmpty())->values();

        $schoolAccounts = SchoolAccount::when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->where('is_active', true)->get();
        $incomes = FinanceIncome::when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->latest()->get();
        $incomeTypes = IncomeType::when($schoolId, fn ($q) => $q->where('school_id', $schoolId))->get();

        return view('pages.keuangan.spp.transaksi', [
            'title' => 'Transaksi Pembayaran SPP & Pemasukan',
            'invoices' => $invoices,
            'students' => $students,
            'activeYear' => $activeYear,
            'arrearsStudents' => $arrearsStudents,
            'schoolAccounts' => $schoolAccounts,
            'incomes' => $incomes,
            'incomeTypes' => $incomeTypes,
        ]);
    }

    public function generateInvoices(Request $request)
    {
        $validated = $request->validate([
            'billing_month' => 'required|date_format:Y-m',
        ]);

        $activeYear = AcademicYear::where('is_active', true)->first();
        if (!$activeYear) {
            return redirect()->route('spp.transaksi.index')->with('error', 'Tidak ada Tahun Akademik yang aktif saat ini.');
        }

        $date = Carbon::createFromFormat('Y-m-d', $validated['billing_month'] . '-01')->startOfDay();
        $monthName = $date->translatedFormat('F'); // e.g. Juli
        $year = $date->year;
        $label = "SPP Bulanan - " . $monthName . " " . $year;

        // Get all active students mapped to classes in this active year
        $classStudents = ClassStudent::whereHas('schoolClass', function ($q) use ($activeYear) {
            $q->where('academic_year_id', $activeYear->id);
        })->with(['student', 'schoolClass'])->get();

        if ($classStudents->isEmpty()) {
            return redirect()->route('spp.transaksi.index')->with('error', 'Tidak ada murid yang terdaftar di kelas aktif pada tahun ajaran ini.');
        }

        $generatedCount = 0;

        DB::transaction(function () use ($classStudents, $activeYear, $date, $label, &$generatedCount) {
            foreach ($classStudents as $cs) {
                $student = $cs->student;
                $class = $cs->schoolClass;

                // Check if an invoice has already been generated for this student and month
                $existing = Invoice::where('student_id', $student->id)
                    ->where('academic_year_id', $activeYear->id)
                    ->whereHas('invoiceItems', function ($q) use ($label) {
                        $q->where('name', $label);
                    })->exists();

                if ($existing) {
                    continue;
                }

                // Find tariff for this class (or fallback to any global tariff for the active year)
                $tariff = SppTariff::where('academic_year_id', $activeYear->id)
                    ->where('type', 'Bulanan')
                    ->where(function ($q) use ($class) {
                        $q->where('school_class_id', $class->id)
                          ->orWhereNull('school_class_id');
                    })
                    ->orderBy('school_class_id', 'desc') // specific class first
                    ->first();

                if (!$tariff) {
                    continue; // Skip if no tariff is set up
                }

                $invNumber = 'INV/' . $date->format('Ym') . '/' . str_pad($student->id, 6, '0', STR_PAD_LEFT);

                $invoice = Invoice::create([
                    'student_id' => $student->id,
                    'academic_year_id' => $activeYear->id,
                    'invoice_number' => $invNumber,
                    'due_date' => $date->copy()->endOfMonth(), // due at end of billing month
                    'total_amount' => $tariff->amount,
                    'status' => 'Belum Lunas'
                ]);

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'spp_tariff_id' => $tariff->id,
                    'name' => $label,
                    'amount' => $tariff->amount
                ]);

                $generatedCount++;
            }
        });

        return redirect()->route('spp.transaksi.index')->with('success', "Berhasil membuat {$generatedCount} tagihan untuk bulan {$monthName} {$year}.");
    }

    public function pay(Request $request, $id)
    {
        $validated = $request->validate([
            'amount_paid' => 'required|numeric|min:1',
            'payment_method' => 'required|in:Tunai,Transfer,Transfer Bank,E-Wallet',
            'payment_date' => 'required|date',
            'account_id' => 'nullable|integer',
        ]);

        $invoice = Invoice::with('transactions')->findOrFail($id);
        $remaining = $invoice->remaining_amount;

        if ((float) $validated['amount_paid'] > $remaining) {
            return redirect()->back()->with('error', 'Jumlah pembayaran melebihi sisa tagihan.');
        }

        $trxData = null;

        DB::transaction(function () use ($invoice, $validated, $remaining, &$trxData) {
            $paymentDate = Carbon::parse($validated['payment_date']);
            $datePrefix = 'TRX' . $paymentDate->format('Ymd');
            $seq = Transaction::where('receipt_number', 'like', $datePrefix . '%')->count() + 1;
            $rcpNumber = $datePrefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            while (Transaction::where('receipt_number', $rcpNumber)->exists()) {
                $seq++;
                $rcpNumber = $datePrefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            }

            $schoolId = $invoice->school_id;

            $tx = Transaction::create([
                'invoice_id' => $invoice->id,
                'school_id' => $schoolId,
                'amount_paid' => $validated['amount_paid'],
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'account_id' => $validated['account_id'] ?? null,
                'receipt_number' => $rcpNumber,
                'recipient_name' => auth()->user() ? auth()->user()->name : 'Bendahara',
                'status' => 'confirmed',
            ]);

            $newRemaining = $remaining - (float) $validated['amount_paid'];

            if ($newRemaining <= 0) {
                $invoice->update(['status' => 'Lunas']);
            } else {
                $invoice->update(['status' => 'Cicilan']);
            }

            // Update School Account (Kas jika Tunai, Bank jika Transfer)
            $targetAccount = null;
            if (!empty($validated['account_id'])) {
                $targetAccount = SchoolAccount::find($validated['account_id']);
            } elseif ($validated['payment_method'] === 'Tunai') {
                $targetAccount = SchoolAccount::where('school_id', $schoolId)->where('type', 'cash')->first();
            } else {
                $targetAccount = SchoolAccount::where('school_id', $schoolId)->where('type', 'bank')->first();
            }

            if ($targetAccount) {
                $targetAccount->increment('current_balance', $validated['amount_paid']);
            }

            // Update Virtual Wallet automatically if fund allocations exist
            if ($schoolId) {
                // Find income type from billing item or invoice item
                $billingItem = BillingItem::where('school_id', $schoolId)
                    ->where(function ($q) use ($invoice) {
                        $q->where('name', $invoice->invoiceItems->first()?->name)
                          ->orWhere('id', $invoice->billing_item_id ?? null);
                    })->first();

                $incomeTypeId = $billingItem?->income_type_id;

                $allocQuery = FundAllocation::where('school_id', $schoolId)->where('status', 'active');
                if ($incomeTypeId && FundAllocation::where('school_id', $schoolId)->where('income_type_id', $incomeTypeId)->where('status', 'active')->exists()) {
                    $allocQuery->where('income_type_id', $incomeTypeId);
                }

                $allocations = $allocQuery->get();

                foreach ($allocations as $alloc) {
                    if ($alloc->virtual_wallet_id) {
                        $allocAmt = in_array(strtolower($alloc->method ?? ''), ['percentage', 'persentase', 'percent', '%'])
                            ? ($validated['amount_paid'] * ($alloc->amount / 100))
                            : min($validated['amount_paid'], (float) $alloc->amount);
                        VirtualWallet::where('id', $alloc->virtual_wallet_id)->increment('nominal', $allocAmt);
                    }
                }
            }

            // Sistem otomatis mengirimkan notifikasi ke orang tua setiap ada transaksi uang masuk
            $student = $invoice->student;
            $parentPhone = $student?->parent_phone 
                ?? $student?->phone 
                ?? $student?->guardianUser?->phone 
                ?? '081234567890';
            $parentName = $student?->parent_name 
                ?? $student?->guardianUser?->name 
                ?? 'Orang Tua/Wali ' . ($student?->name ?? 'Siswa');

            $formattedAmount = 'Rp ' . number_format($validated['amount_paid'], 0, ',', '.');
            $newRemainingFormatted = 'Rp ' . number_format(max(0, $newRemaining), 0, ',', '.');
            $statusNotification = $newRemaining <= 0 ? 'Lunas' : 'Cicilan (Sisa: ' . $newRemainingFormatted . ')';

            $notificationMessage = "Yth. Bpk/Ibu {$parentName},\n"
                . "Pembayaran untuk murid {$student?->name} (NIS: {$student?->nis}) telah berhasil diterima pada " . $paymentDate->translatedFormat('d F Y') . ".\n"
                . "No. Transaksi: {$rcpNumber}\n"
                . "Nominal: {$formattedAmount}\n"
                . "Metode: {$validated['payment_method']}\n"
                . "Status: {$statusNotification}\n"
                . "Terima kasih atas pembayaran Anda.";

            try {
                app(NotificationService::class)->send('whatsapp', $parentPhone, $notificationMessage);
            } catch (\Throwable $e) {
                Log::warning("Gagal mengirim notifikasi WhatsApp transaksi uang masuk: " . $e->getMessage());
            }

            Log::info("Notifikasi WhatsApp uang masuk terkirim ke orang tua {$student?->name} ({$parentPhone}) untuk transaksi {$rcpNumber}");

            $trxData = [
                'rcp_number' => $rcpNumber,
                'amount' => $validated['amount_paid'],
                'method' => $validated['payment_method'],
                'date' => $paymentDate->translatedFormat('d F Y'),
                'account_name' => $targetAccount ? ($targetAccount->bank_name ? $targetAccount->bank_name . ' - ' . $targetAccount->account_number : $targetAccount->account_name) : 'Kas Sekolah',
                'is_lunas' => $newRemaining <= 0,
                'parent_phone' => $parentPhone,
            ];
        });

        return redirect()->back()
            ->with('success', 'Transaksi berhasil disimpan')
            ->with('payment_success_modal', true)
            ->with('trx_number', $trxData['rcp_number'] ?? 'TRX' . date('Ymd0001'))
            ->with('trx_amount', $trxData['amount'] ?? 0)
            ->with('trx_method', $trxData['method'] ?? 'Tunai')
            ->with('trx_account', $trxData['account_name'] ?? 'Kas Sekolah')
            ->with('trx_date', $trxData['date'] ?? date('d F Y'))
            ->with('trx_student_name', $invoice->student?->name ?? 'Siswa')
            ->with('trx_student_nis', $invoice->student?->nis ?? '-')
            ->with('trx_class', $invoice->student?->schoolClasses->first()?->name ?? 'Kelas X')
            ->with('trx_item_name', $invoice->invoiceItems->first()?->name ?? 'SPP Komite')
            ->with('trx_period', $invoice->due_date ? $invoice->due_date->translatedFormat('F Y') : 'Juli 2025')
            ->with('parent_notification_sent', true)
            ->with('parent_phone', $trxData['parent_phone'] ?? '');
    }
}
