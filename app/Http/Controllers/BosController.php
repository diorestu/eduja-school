<?php

namespace App\Http\Controllers;

use App\Models\BudgetCategory;
use App\Models\Expense;
use App\Models\AcademicYear;
use App\Models\Transaction;
use App\Models\ApprovalRequest;
use App\Services\FinanceLedgerService;
use App\Services\FinanceService;
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
    public function storeBelanja(Request $request, SchoolContext $schoolContext, FinanceService $finance)
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
        $finance->createExpense($schoolId, $request->user()->id, $validated);

        return redirect()->back()->with('success', 'Pengeluaran dibuat dan menunggu approval kepala sekolah.');
    }

    /**
     * Display Buku Kas Umum (BKU) ledger.
     */
    public function bku(Request $request, SchoolContext $schoolContext, FinanceLedgerService $ledger)
    {
        $schoolId = $schoolContext->activeSchoolId();
        abort_unless($schoolId, 403);

        return view('pages.keuangan.bos.bku', [
            'title' => 'Buku Kas Umum (BKU) Sekolah',
            ...$ledger->bku($schoolId, [
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'source_funding' => $request->input('source_funding'),
                'account_id' => $request->input('account_id'),
            ]),
        ]);
    }
}
