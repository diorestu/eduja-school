<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Transaction;
use App\Models\Student;
use App\Models\SppTariff;
use App\Models\AcademicYear;
use App\Models\ClassStudent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class SppTransactionController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::with(['student.schoolClasses', 'transactions'])
            ->orderBy('created_at', 'desc');

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $invoices->whereHas('student', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        $invoices = $invoices->get();
        $students = Student::where('is_active', true)->orderBy('name', 'asc')->get();
        $activeYear = AcademicYear::where('is_active', true)->first();

        return view('pages.keuangan.spp.transaksi', [
            'title' => 'Transaksi Pembayaran SPP',
            'invoices' => $invoices,
            'students' => $students,
            'activeYear' => $activeYear
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
            return redirect()->route('spp.transaksi.index')->with('error', 'Tidak ada siswa yang terdaftar di kelas aktif pada tahun ajaran ini.');
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
            'payment_method' => 'required|in:Tunai,Transfer Bank,E-Wallet',
            'payment_date' => 'required|date'
        ]);

        $invoice = Invoice::with('transactions')->findOrFail($id);
        $remaining = $invoice->remaining_amount;

        if ((float) $validated['amount_paid'] > $remaining) {
            return redirect()->back()->with('error', 'Jumlah pembayaran melebihi sisa tagihan.');
        }

        DB::transaction(function () use ($invoice, $validated, $remaining) {
            $paymentDate = Carbon::parse($validated['payment_date']);
            $nextSequence = Transaction::where('invoice_id', $invoice->id)->count() + 1;
            $rcpNumber = 'RCP/' . $paymentDate->format('Ymd') . '/' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT) . '/' . str_pad($nextSequence, 3, '0', STR_PAD_LEFT);

            Transaction::create([
                'invoice_id' => $invoice->id,
                'amount_paid' => $validated['amount_paid'],
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'receipt_number' => $rcpNumber,
                'recipient_name' => auth()->user() ? auth()->user()->name : 'Sistem'
            ]);

            $newRemaining = $remaining - (float) $validated['amount_paid'];

            if ($newRemaining <= 0) {
                $invoice->update(['status' => 'Lunas']);
            } else {
                $invoice->update(['status' => 'Cicilan']);
            }
        });

        return redirect()->route('spp.transaksi.index')->with('success', 'Pembayaran berhasil dicatat.');
    }
}
