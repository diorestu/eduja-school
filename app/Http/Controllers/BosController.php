<?php

namespace App\Http\Controllers;

use App\Models\BudgetCategory;
use App\Models\Expense;
use App\Models\AcademicYear;
use App\Models\Transaction;
use App\Models\ApprovalRequest;
use App\Services\SchoolContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BosController extends Controller
{
    /**
     * Display RKAS Budget categories and alocations.
     */
    public function anggaran()
    {
        $categories = BudgetCategory::orderBy('code')->get();
        return view('pages.keuangan.bos.anggaran', [
            'title' => 'Rencana Kegiatan & Anggaran Sekolah (RKAS)',
            'categories' => $categories
        ]);
    }

    /**
     * Store new budget category.
     */
    public function storeAnggaran(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:budget_categories,code',
            'name' => 'required|string|max:255',
            'source_funding' => 'required|string'
        ]);

        BudgetCategory::create($validated);

        return redirect()->back()->with('success', 'Kategori Anggaran RKAS berhasil ditambahkan!');
    }

    /**
     * Display list of expenses and record form.
     */
    public function belanja(SchoolContext $schoolContext)
    {
        $schoolId = $schoolContext->activeSchoolId();
        $expenses = Expense::with(['budgetCategory', 'academicYear'])
            ->where('school_id', $schoolId)
            ->orderBy('transaction_date', 'desc')
            ->get();
            
        $categories = BudgetCategory::where('is_active', true)->orderBy('code')->get();
        $academicYears = AcademicYear::where('school_id', $schoolId)->get();
        $activeYear = AcademicYear::where('school_id', $schoolId)->where('is_active', true)->first();

        return view('pages.keuangan.bos.belanja', [
            'title' => 'Pencatatan Belanja & Operasional Sekolah',
            'expenses' => $expenses,
            'categories' => $categories,
            'academicYears' => $academicYears,
            'activeYear' => $activeYear
        ]);
    }

    /**
     * Store new expense with tax calculations.
     */
    public function storeBelanja(Request $request, SchoolContext $schoolContext)
    {
        $validated = $request->validate([
            'budget_category_id' => 'nullable|exists:budget_categories,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'expense_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'source_funding' => 'required|string',
            'payment_method' => 'required|string',
            'reference_invoice' => 'nullable|string',
            'recipient_name' => 'nullable|string',
            'tax_type' => 'nullable|string',
            'tax_amount' => 'nullable|numeric|min:0',
            'is_tax_paid' => 'nullable|boolean'
        ]);

        // Default tax amount if null
        $validated['tax_amount'] = $request->has('tax_amount') ? $request->input('tax_amount') : 0.00;
        $validated['is_tax_paid'] = $request->has('is_tax_paid') ? (bool) $request->input('is_tax_paid') : false;

        $schoolId = $schoolContext->activeSchoolId();
        abort_unless($schoolId, 403);
        $expense = DB::transaction(function () use ($validated, $schoolId, $request) {
            $expense = Expense::create(array_merge($validated, ['school_id' => $schoolId, 'status' => 'pending']));
            ApprovalRequest::create([
                'school_id' => $schoolId,
                'requested_by' => $request->user()->id,
                'approvable_type' => Expense::class,
                'approvable_id' => $expense->id,
                'type' => 'expense',
                'status' => 'pending',
            ]);
            return $expense;
        });

        return redirect()->back()->with('success', 'Pengeluaran dibuat dan menunggu approval kepala sekolah.');
    }

    /**
     * Display Buku Kas Umum (BKU) ledger.
     */
    public function bku()
    {
        // 1. Gather all SPP Payments (Penerimaan SPP)
        $sppPayments = Transaction::with(['invoice.student'])->get();

        // 2. Gather all Expenses (Pengeluaran Operasional)
        $expenses = Expense::where('status', 'approved')->where('school_id', app(SchoolContext::class)->activeSchoolId())->get();

        // ==========================================
        // 3. COMPILE BUKU KAS UMUM (BKU) LEDGER
        // ==========================================
        $ledger = [];

        foreach ($sppPayments as $payment) {
            $ledger[] = [
                'date' => $payment->payment_date,
                'ref' => $payment->receipt_number,
                'description' => 'Penerimaan SPP - ' . ($payment->invoice->student->name ?? 'Siswa'),
                'debet' => (float) $payment->amount_paid,
                'kredit' => 0.00
            ];
        }

        foreach ($expenses as $exp) {
            $ledger[] = [
                'date' => $exp->transaction_date,
                'ref' => $exp->reference_invoice ?? 'EXP-' . $exp->id,
                'description' => $exp->expense_name . ' (' . $exp->source_funding . ')',
                'debet' => 0.00,
                'kredit' => (float) $exp->amount
            ];

            if ($exp->tax_amount > 0) {
                $ledger[] = [
                    'date' => $exp->transaction_date,
                    'ref' => ($exp->reference_invoice ?? 'EXP') . '-TAX',
                    'description' => 'Penerimaan Pajak ' . $exp->tax_type . ' - ' . $exp->expense_name,
                    'debet' => (float) $exp->tax_amount,
                    'kredit' => 0.00
                ];

                if ($exp->is_tax_paid) {
                    $ledger[] = [
                        'date' => $exp->transaction_date,
                        'ref' => ($exp->reference_invoice ?? 'EXP') . '-SSP',
                        'description' => 'Penyetoran Pajak ' . $exp->tax_type . ' - ' . $exp->expense_name,
                        'debet' => 0.00,
                        'kredit' => (float) $exp->tax_amount
                    ];
                }
            }
        }

        usort($ledger, function($a, $b) {
            return strcmp($a['date']->format('Y-m-d'), $b['date']->format('Y-m-d'));
        });

        $runningBalance = 0.00;
        foreach ($ledger as &$entry) {
            $runningBalance += $entry['debet'] - $entry['kredit'];
            $entry['saldo'] = $runningBalance;
        }

        // ==========================================
        // 4. COMPILE BUKU PEMBANTU KAS (TUNAI)
        // ==========================================
        $bukuKas = [];

        foreach ($sppPayments as $payment) {
            if ($payment->payment_method === 'Tunai') {
                $bukuKas[] = [
                    'date' => $payment->payment_date,
                    'ref' => $payment->receipt_number,
                    'description' => 'Penerimaan SPP - ' . ($payment->invoice->student->name ?? 'Siswa'),
                    'debet' => (float) $payment->amount_paid,
                    'kredit' => 0.00
                ];
            }
        }

        foreach ($expenses as $exp) {
            if ($exp->payment_method === 'Tunai') {
                $bukuKas[] = [
                    'date' => $exp->transaction_date,
                    'ref' => $exp->reference_invoice ?? 'EXP-' . $exp->id,
                    'description' => $exp->expense_name . ' (Tunai)',
                    'debet' => 0.00,
                    'kredit' => (float) $exp->amount
                ];

                if ($exp->tax_amount > 0) {
                    $bukuKas[] = [
                        'date' => $exp->transaction_date,
                        'ref' => ($exp->reference_invoice ?? 'EXP') . '-TAX',
                        'description' => 'Penerimaan Pajak ' . $exp->tax_type . ' - ' . $exp->expense_name,
                        'debet' => (float) $exp->tax_amount,
                        'kredit' => 0.00
                    ];

                    if ($exp->is_tax_paid) {
                        $bukuKas[] = [
                            'date' => $exp->transaction_date,
                            'ref' => ($exp->reference_invoice ?? 'EXP') . '-SSP',
                            'description' => 'Penyetoran Pajak ' . $exp->tax_type . ' - ' . $exp->expense_name,
                            'debet' => 0.00,
                            'kredit' => (float) $exp->tax_amount
                        ];
                    }
                }
            }
        }

        usort($bukuKas, function($a, $b) {
            return strcmp($a['date']->format('Y-m-d'), $b['date']->format('Y-m-d'));
        });

        $runningBalanceKas = 0.00;
        foreach ($bukuKas as &$entry) {
            $runningBalanceKas += $entry['debet'] - $entry['kredit'];
            $entry['saldo'] = $runningBalanceKas;
        }

        // ==========================================
        // 5. COMPILE BUKU PEMBANTU BANK (TRANSFER)
        // ==========================================
        $bukuBank = [];

        foreach ($sppPayments as $payment) {
            if ($payment->payment_method !== 'Tunai') {
                $bukuBank[] = [
                    'date' => $payment->payment_date,
                    'ref' => $payment->receipt_number,
                    'description' => 'Penerimaan SPP - ' . ($payment->invoice->student->name ?? 'Siswa') . ' (Transfer)',
                    'debet' => (float) $payment->amount_paid,
                    'kredit' => 0.00
                ];
            }
        }

        foreach ($expenses as $exp) {
            if ($exp->payment_method !== 'Tunai') {
                $bukuBank[] = [
                    'date' => $exp->transaction_date,
                    'ref' => $exp->reference_invoice ?? 'EXP-' . $exp->id,
                    'description' => $exp->expense_name . ' (Transfer Bank)',
                    'debet' => 0.00,
                    'kredit' => (float) $exp->amount
                ];

                if ($exp->tax_amount > 0) {
                    $bukuBank[] = [
                        'date' => $exp->transaction_date,
                        'ref' => ($exp->reference_invoice ?? 'EXP') . '-TAX',
                        'description' => 'Penerimaan Pajak ' . $exp->tax_type . ' - ' . $exp->expense_name,
                        'debet' => (float) $exp->tax_amount,
                        'kredit' => 0.00
                    ];

                    if ($exp->is_tax_paid) {
                        $bukuBank[] = [
                            'date' => $exp->transaction_date,
                            'ref' => ($exp->reference_invoice ?? 'EXP') . '-SSP',
                            'description' => 'Penyetoran Pajak ' . $exp->tax_type . ' - ' . $exp->expense_name,
                            'debet' => 0.00,
                            'kredit' => (float) $exp->tax_amount
                        ];
                    }
                }
            }
        }

        usort($bukuBank, function($a, $b) {
            return strcmp($a['date']->format('Y-m-d'), $b['date']->format('Y-m-d'));
        });

        $runningBalanceBank = 0.00;
        foreach ($bukuBank as &$entry) {
            $runningBalanceBank += $entry['debet'] - $entry['kredit'];
            $entry['saldo'] = $runningBalanceBank;
        }

        // ==========================================
        // 6. COMPILE BUKU PEMBANTU PAJAK (TAXES)
        // ==========================================
        $bukuPajak = [];

        foreach ($expenses as $exp) {
            if ($exp->tax_amount > 0) {
                // Withholding (Penerimaan Pajak)
                $bukuPajak[] = [
                    'date' => $exp->transaction_date,
                    'ref' => ($exp->reference_invoice ?? 'EXP') . '-TAX',
                    'description' => 'Penerimaan Pajak ' . $exp->tax_type . ' - ' . $exp->expense_name,
                    'debet' => (float) $exp->tax_amount,
                    'kredit' => 0.00
                ];

                // Deposit (Penyetoran Pajak)
                if ($exp->is_tax_paid) {
                    $bukuPajak[] = [
                        'date' => $exp->transaction_date,
                        'ref' => ($exp->reference_invoice ?? 'EXP') . '-SSP',
                        'description' => 'Penyetoran Pajak ' . $exp->tax_type . ' - ' . $exp->expense_name,
                        'debet' => 0.00,
                        'kredit' => (float) $exp->tax_amount
                    ];
                }
            }
        }

        usort($bukuPajak, function($a, $b) {
            return strcmp($a['date']->format('Y-m-d'), $b['date']->format('Y-m-d'));
        });

        $runningBalancePajak = 0.00;
        foreach ($bukuPajak as &$entry) {
            $runningBalancePajak += $entry['debet'] - $entry['kredit'];
            $entry['saldo'] = $runningBalancePajak;
        }

        return view('pages.keuangan.bos.bku', [
            'title' => 'Buku Kas Umum (BKU) Sekolah',
            
            // Ledgers
            'ledger' => $ledger,
            'bukuKas' => $bukuKas,
            'bukuBank' => $bukuBank,
            'bukuPajak' => $bukuPajak,
            
            // General Balances
            'totalDebet' => array_sum(array_column($ledger, 'debet')),
            'totalKredit' => array_sum(array_column($ledger, 'kredit')),
            'finalBalance' => $runningBalance,

            // Sub-ledger Balances
            'balanceKas' => $runningBalanceKas,
            'balanceBank' => $runningBalanceBank,
            'balancePajak' => $runningBalancePajak
        ]);
    }
}
