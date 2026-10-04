<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\BillingItem;
use App\Models\BookClosing;
use App\Models\BudgetPlan;
use App\Models\BudgetPlanRevision;
use App\Models\BudgetYear;
use App\Models\ExpenseType;
use App\Models\FundAllocation;
use App\Models\IncomeType;
use App\Models\PaymentSubmission;
use App\Models\SchoolAccount;
use App\Services\FinanceClosingService;
use App\Services\FinanceLedgerService;
use App\Services\FinanceService;
use App\Services\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinanceFoundationController extends Controller
{
    public function accounts(SchoolContext $schoolContext, FinanceLedgerService $ledger): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();

        return view('pages.keuangan.accounts', [
            'title' => 'Rekening & Wallet Sekolah',
            'accounts' => SchoolAccount::where('school_id', $schoolId)->latest()->get(),
            'eyebrow' => 'Finance Master',
            'description' => 'Daftar rekening kas, bank, dan wallet virtual sebagai sumber saldo ledger.',
            'metrics' => $this->financeMetrics($ledger, $schoolId),
            'rows' => SchoolAccount::where('school_id', $schoolId)->latest()->get(['name', 'type', 'bank_name', 'current_balance', 'is_active']),
            'columns' => ['name' => 'Nama', 'type' => 'Tipe', 'bank_name' => 'Bank', 'current_balance' => 'Saldo', 'is_active' => 'Aktif'],
            'form' => [
                'action' => route('finance.accounts.store'),
                'fields' => [
                    ['name' => 'name', 'label' => 'Nama Rekening', 'placeholder' => 'Kas Bendahara'],
                    ['name' => 'type', 'label' => 'Tipe', 'placeholder' => 'Tunai / Bank / Wallet'],
                    ['name' => 'bank_name', 'label' => 'Bank', 'placeholder' => 'BRI'],
                ],
                'button' => 'Tambah Rekening',
            ],
        ]);
    }

    public function storeAccount(Request $request, SchoolContext $schoolContext, FinanceService $finance): RedirectResponse
    {
        $schoolId = $this->activeSchoolId($request, $schoolContext);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['Tunai', 'Bank'])],
            'bank_name' => ['exclude_unless:type,Bank', 'required', 'string', 'max:80'],
            'account_number' => ['exclude_unless:type,Bank', 'required', 'string', 'max:100'],
            'opening_balance' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
        ]);

        $finance->createAccount($schoolId, $validated);

        return redirect()->route('finance.accounts')->with('success', 'Rekening Sekolah disimpan');
    }

    public function updateAccount(Request $request, SchoolAccount $account, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        if ((int) $account->school_id !== (int) $schoolId) {
            abort(403, 'Aksi tidak diizinkan untuk sekolah ini.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['Tunai', 'Bank'])],
            'bank_name' => ['exclude_unless:type,Bank', 'required', 'string', 'max:80'],
            'account_number' => ['exclude_unless:type,Bank', 'required', 'string', 'max:100'],
            'is_active' => ['nullable'],
        ]);

        $account->update([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'bank_name' => $validated['type'] === 'Bank' ? ($validated['bank_name'] ?? null) : null,
            'account_number' => $validated['type'] === 'Bank' ? ($validated['account_number'] ?? null) : null,
            'is_active' => filter_var($request->input('is_active', true), FILTER_VALIDATE_BOOLEAN),
        ]);

        return redirect()->route('finance.accounts')->with('success', 'Rekening Sekolah berhasil diperbarui.');
    }

    public function incomeTypes(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();

        $incomeTypes = IncomeType::with('fundAllocations.account')
            ->where('school_id', $schoolId)
            ->latest()
            ->get();

        $count = IncomeType::where('school_id', $schoolId)->count();
        $codeNumber = $count + 1;
        do {
            $nextCode = 'JP'.str_pad((string) $codeNumber, 4, '0', STR_PAD_LEFT);
            $codeNumber++;
        } while (IncomeType::where('school_id', $schoolId)->where('code', $nextCode)->exists());

        $categories = ['Komite', 'BOS', 'Hibah', 'Lainnya'];
        $accounts = SchoolAccount::where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'bank_name', 'account_number']);

        return view('pages.keuangan.income-types', [
            'title' => 'Jenis Pemasukan',
            'incomeTypes' => $incomeTypes,
            'nextCode' => $nextCode,
            'categories' => $categories,
            'accounts' => $accounts,
        ]);
    }

    public function expenseTypes(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();

        return $this->typePage('Jenis Pengeluaran', 'expense', ExpenseType::where('school_id', $schoolId)->latest()->get(['code', 'name', 'source_funding', 'requires_approval']));
    }

    public function storeIncomeType(Request $request, SchoolContext $schoolContext, FinanceService $finance): RedirectResponse
    {
        $schoolId = $this->activeSchoolId($request, $schoolContext);
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('income_types')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'code' => ['nullable', 'string', 'max:40'],
            'category' => ['required', 'string', 'max:50'],
            'uses_allocation' => ['nullable'],
            'requires_approval' => ['nullable'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.name' => ['nullable', 'string', 'max:120'],
            'allocations.*.method' => ['nullable', 'string', Rule::in(['persentase', 'nominal'])],
            'allocations.*.amount' => ['nullable', 'numeric', 'min:0'],
            'allocations.*.account_id' => ['nullable', 'integer', Rule::exists('school_accounts', 'id')->where(fn ($query) => $query->where('school_id', $schoolId))],
        ], [
            'name.required' => 'Nama jenis pemasukan wajib diisi.',
            'name.unique' => 'Jenis pemasukan dengan nama "'.$request->input('name').'" sudah ada.',
            'category.required' => 'Kategori wajib dipilih.',
        ]);

        if (empty($validated['code'])) {
            $count = IncomeType::where('school_id', $schoolId)->count();
            $codeNumber = $count + 1;
            do {
                $code = 'JP'.str_pad((string) $codeNumber, 4, '0', STR_PAD_LEFT);
                $codeNumber++;
            } while (IncomeType::where('school_id', $schoolId)->where('code', $code)->exists());
            $validated['code'] = $code;
        }

        $validated['uses_allocation'] = filter_var($validated['uses_allocation'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $validated['requires_approval'] = filter_var($validated['requires_approval'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $incomeType = $finance->createIncomeType($schoolId, $validated);

        if ($validated['uses_allocation'] && !empty($request->input('allocations'))) {
            foreach ($request->input('allocations') as $alloc) {
                if (!empty($alloc['name']) && !empty($alloc['account_id'])) {
                    FundAllocation::create([
                        'school_id' => $schoolId,
                        'income_type_id' => $incomeType->id,
                        'account_id' => $alloc['account_id'],
                        'name' => $alloc['name'],
                        'method' => $alloc['method'] ?? 'persentase',
                        'amount' => $alloc['amount'] ?? 0,
                        'status' => 'active',
                    ]);
                }
            }
        }

        return redirect()->route('finance.income-types')
            ->with('success_modal', true)
            ->with('success', 'Data jenis pemasukan berhasil disimpan ke dalam sistem.');
    }

    public function updateIncomeType(Request $request, IncomeType $incomeType, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        if ((int) $incomeType->school_id !== (int) $schoolId) {
            abort(403, 'Aksi tidak diizinkan untuk sekolah ini.');
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('income_types')->where(fn ($query) => $query->where('school_id', $schoolId))->ignore($incomeType->id),
            ],
            'category' => ['required', 'string', 'max:50'],
            'uses_allocation' => ['nullable'],
            'requires_approval' => ['nullable'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.name' => ['nullable', 'string', 'max:120'],
            'allocations.*.method' => ['nullable', 'string', Rule::in(['persentase', 'nominal'])],
            'allocations.*.amount' => ['nullable', 'numeric', 'min:0'],
            'allocations.*.account_id' => ['nullable', 'integer', Rule::exists('school_accounts', 'id')->where(fn ($query) => $query->where('school_id', $schoolId))],
        ], [
            'name.required' => 'Nama jenis pemasukan wajib diisi.',
            'name.unique' => 'Jenis pemasukan dengan nama "'.$request->input('name').'" sudah ada.',
            'category.required' => 'Kategori wajib dipilih.',
        ]);

        $usesAllocation = filter_var($request->input('uses_allocation', false), FILTER_VALIDATE_BOOLEAN);
        $incomeType->update([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'uses_allocation' => $usesAllocation,
            'requires_approval' => filter_var($request->input('requires_approval', false), FILTER_VALIDATE_BOOLEAN),
        ]);

        if ($usesAllocation) {
            $incomeType->fundAllocations()->delete();
            if (!empty($request->input('allocations'))) {
                foreach ($request->input('allocations') as $alloc) {
                    if (!empty($alloc['name']) && !empty($alloc['account_id'])) {
                        FundAllocation::create([
                            'school_id' => $schoolId,
                            'income_type_id' => $incomeType->id,
                            'account_id' => $alloc['account_id'],
                            'name' => $alloc['name'],
                            'method' => $alloc['method'] ?? 'persentase',
                            'amount' => $alloc['amount'] ?? 0,
                            'status' => 'active',
                        ]);
                    }
                }
            }
        } else {
            $incomeType->fundAllocations()->delete();
        }

        return redirect()->route('finance.income-types')
            ->with('success', 'Data jenis pemasukan berhasil diperbarui.');
    }

    public function destroyIncomeType(IncomeType $incomeType, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        if ((int) $incomeType->school_id !== (int) $schoolId) {
            abort(403, 'Aksi tidak diizinkan untuk sekolah ini.');
        }

        $incomeType->delete();

        return redirect()->route('finance.income-types')->with('success', 'Jenis pemasukan berhasil dihapus.');
    }

    public function storeExpenseType(Request $request, SchoolContext $schoolContext, FinanceService $finance): RedirectResponse
    {
        $schoolId = $this->activeSchoolId($request, $schoolContext);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40'],
            'source_funding' => ['nullable', 'string', 'max:50'],
            'bos_component' => ['nullable', 'string', 'max:100'],
            'requires_approval' => ['nullable', 'boolean'],
        ]);

        $finance->createExpenseType($schoolId, $validated);

        return back()->with('success', 'Jenis pengeluaran berhasil ditambahkan.');
    }

    public function allocations(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();

        return view('pages.foundation.index', [
            'title' => 'Alokasi Dana',
            'eyebrow' => 'Finance Master',
            'description' => 'Aturan alokasi sumber pemasukan ke rekening sekolah.',
            'rows' => FundAllocation::query()
                ->with(['incomeType:id,name', 'account:id,name'])
                ->where('school_id', $schoolId)
                ->latest()
                ->get(),
            'columns' => ['name' => 'Nama', 'incomeType.name' => 'Jenis Pemasukan', 'account.name' => 'Rekening', 'method' => 'Metode', 'amount' => 'Nominal', 'status' => 'Status'],
        ]);
    }

    public function storeAllocation(Request $request, SchoolContext $schoolContext, FinanceService $finance): RedirectResponse
    {
        $schoolId = $this->activeSchoolId($request, $schoolContext);
        $validated = $request->validate([
            'income_type_id' => ['required', 'integer', Rule::exists('income_types', 'id')->where(fn ($query) => $query->where('school_id', $schoolId))],
            'account_id' => ['required', 'integer', Rule::exists('school_accounts', 'id')->where(fn ($query) => $query->where('school_id', $schoolId))],
            'name' => ['required', 'string', 'max:120'],
            'method' => ['nullable', 'string', 'max:40'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $finance->createFundAllocation($schoolId, $validated);

        return back()->with('success', 'Alokasi dana berhasil ditambahkan.');
    }

    public function budgetYears(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();

        return view('pages.keuangan.budget-years', [
            'budgetYears' => BudgetYear::where('school_id', $schoolId)->latest('start_date')->get(),
        ]);
    }

    public function storeBudgetYear(Request $request, SchoolContext $schoolContext, FinanceService $finance): RedirectResponse
    {
        $schoolId = $this->activeSchoolId($request, $schoolContext);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $finance->createBudgetYear($schoolId, $validated);

        return back()->with('success', 'Tahun anggaran berhasil ditambahkan.');
    }

    public function updateBudgetYear(Request $request, BudgetYear $budgetYear, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $this->activeSchoolId($request, $schoolContext);
        abort_unless((int) $budgetYear->school_id === $schoolId, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'string', Rule::in(['active', 'closed'])],
        ]);

        $budgetYear->update($validated);

        return back()->with('success', 'Tahun anggaran berhasil diperbarui.');
    }

    public function budgets(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();

        $budgetYears = BudgetYear::where('school_id', $schoolId)->orderByDesc('start_date')->get();
        $budgetPlans = BudgetPlan::with(['budgetYear', 'revisions'])
            ->where('school_id', $schoolId)
            ->latest()
            ->get();

        $metrics = [
            ['label' => 'Tahun Anggaran', 'value' => $budgetYears->count()],
            ['label' => 'Total Rencana', 'value' => $budgetPlans->count()],
            ['label' => 'Pending Approval', 'value' => $budgetPlans->where('status', 'pending')->count()],
            ['label' => 'Total Anggaran', 'value' => 'Rp ' . number_format($budgetPlans->sum('amount'), 0, ',', '.')],
        ];

        return view('pages.keuangan.budgets', [
            'budgetYears' => $budgetYears,
            'budgetPlans' => $budgetPlans,
            'metrics' => $metrics,
        ]);
    }

    public function budgetRevisions(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();

        $budgetYears = BudgetYear::where('school_id', $schoolId)->orderByDesc('start_date')->get();
        $budgetPlans = BudgetPlan::with(['budgetYear', 'revisions'])
            ->where('school_id', $schoolId)
            ->latest()
            ->get();

        $allRevisions = BudgetPlanRevision::with(['budgetPlan.budgetYear'])
            ->where('school_id', $schoolId)
            ->latest()
            ->get();

        $metrics = [
            ['label' => 'Total Rencana Anggaran', 'value' => $budgetPlans->count()],
            ['label' => 'Total Usulan Revisi', 'value' => $allRevisions->count()],
            ['label' => 'Revisi Pending', 'value' => $allRevisions->where('status', 'pending')->count()],
            ['label' => 'Revisi Disetujui', 'value' => $allRevisions->where('status', 'approved')->count()],
        ];

        return view('pages.keuangan.budget-revisions', [
            'budgetYears' => $budgetYears,
            'budgetPlans' => $budgetPlans,
            'revisions' => $allRevisions,
            'metrics' => $metrics,
        ]);
    }

    public function updateBudget(Request $request, BudgetPlan $budgetPlan, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $this->activeSchoolId($request, $schoolContext);
        abort_unless((int) $budgetPlan->school_id === $schoolId, 404);

        $validated = $request->validate([
            'budget_year_id' => ['required', 'integer', Rule::exists('budget_years', 'id')->where(fn ($query) => $query->where('school_id', $schoolId))],
            'source_funding' => ['required', 'string', 'max:50'],
            'program_name' => ['required', 'string', 'max:160'],
            'activity_name' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $budgetPlan->update($validated);

        return back()->with('success', 'Rencana anggaran berhasil diperbarui.');
    }

    public function storeBudget(Request $request, SchoolContext $schoolContext, FinanceService $finance): RedirectResponse
    {
        $schoolId = $this->activeSchoolId($request, $schoolContext);
        $validated = $request->validate([
            'budget_year_id' => ['required', 'integer', Rule::exists('budget_years', 'id')->where(fn ($query) => $query->where('school_id', $schoolId))],
            'source_funding' => ['required', 'string', 'max:50'],
            'program_name' => ['required', 'string', 'max:160'],
            'activity_name' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $finance->createBudgetPlan($schoolId, $request->user()->id, $validated);

        return back()->with('success', 'Rencana anggaran berhasil dibuat dan menunggu approval.');
    }

    public function storeBudgetRevision(Request $request, BudgetPlan $budgetPlan, SchoolContext $schoolContext, FinanceService $finance): RedirectResponse
    {
        $schoolId = $this->activeSchoolId($request, $schoolContext);
        abort_unless((int) $budgetPlan->school_id === $schoolId, 404);

        $validated = $request->validate([
            'new_amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $finance->reviseBudgetPlan($schoolId, $request->user()->id, $budgetPlan->id, $validated);

        return back()->with('success', 'Revisi anggaran berhasil dibuat dan menunggu approval.');
    }

    public function approvals(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolId();
        $approvals = ApprovalRequest::with(['requester', 'reviewer'])
            ->where('school_id', $schoolId)
            ->latest()
            ->get();

        $metrics = [
            ['label' => 'Total Pengajuan', 'value' => $approvals->count()],
            ['label' => 'Pending Approval', 'value' => $approvals->where('status', 'pending')->count()],
            ['label' => 'Disetujui', 'value' => $approvals->where('status', 'approved')->count()],
            ['label' => 'Ditolak', 'value' => $approvals->where('status', 'rejected')->count()],
        ];

        return view('pages.keuangan.approvals', [
            'approvals' => $approvals,
            'metrics' => $metrics,
        ]);
    }

    public function billing(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();

        $billingItems = BillingItem::with('incomeType')
            ->where('school_id', $schoolId)
            ->latest()
            ->get();
        $incomeTypes = IncomeType::where('school_id', $schoolId)->orderBy('name')->get();

        $metrics = [
            ['label' => 'Total Item Tagihan', 'value' => $billingItems->count()],
            ['label' => 'Total Nominal', 'value' => 'Rp ' . number_format($billingItems->sum('amount'), 0, ',', '.')],
            ['label' => 'Transfer Pending', 'value' => PaymentSubmission::where('school_id', $schoolId)->where('status', 'pending')->count()],
        ];

        return view('pages.keuangan.billing', [
            'billingItems' => $billingItems,
            'incomeTypes' => $incomeTypes,
            'metrics' => $metrics,
        ]);
    }

    public function storeBilling(Request $request, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $this->activeSchoolId($request, $schoolContext);
        $validated = $request->validate([
            'income_type_id' => ['nullable', 'integer', Rule::exists('income_types', 'id')->where(fn ($query) => $query->where('school_id', $schoolId))],
            'name' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0'],
            'billing_frequency' => ['nullable', 'string', 'max:40'],
            'due_date' => ['nullable', 'date'],
            'target_type' => ['nullable', 'string', 'max:40'],
            'target_id' => ['nullable', 'integer'],
        ]);

        BillingItem::create([
            ...$validated,
            'school_id' => $schoolId,
            'status' => 'active',
        ]);

        return back()->with('success', 'Tagihan berhasil disimpan.');
    }

    public function updateBilling(Request $request, BillingItem $billingItem, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $this->activeSchoolId($request, $schoolContext);
        abort_unless((int) $billingItem->school_id === $schoolId, 404);

        $validated = $request->validate([
            'income_type_id' => ['nullable', 'integer', Rule::exists('income_types', 'id')->where(fn ($query) => $query->where('school_id', $schoolId))],
            'name' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0'],
            'billing_frequency' => ['nullable', 'string', 'max:40'],
            'due_date' => ['nullable', 'date'],
            'target_type' => ['nullable', 'string', 'max:40'],
            'status' => ['required', 'string', Rule::in(['draft', 'active', 'closed'])],
        ]);

        $billingItem->update($validated);

        return back()->with('success', 'Tagihan berhasil diperbarui.');
    }

    public function closing(SchoolContext $schoolContext, FinanceClosingService $closingService): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();

        $validation = $closingService->validate($schoolId);
        $closings = BookClosing::with('closedBy')
            ->where('school_id', $schoolId)
            ->latest('closed_at')
            ->get();

        $accounts = SchoolAccount::where('school_id', $schoolId)
            ->orderBy('id')
            ->get();

        $lastClosing = $closings->first();

        $metrics = [
            ['label' => 'Total Periode Ditutup', 'value' => $closings->count().' periode'],
            ['label' => 'Tutup Buku Terakhir', 'value' => $lastClosing ? $lastClosing->period : 'Belum Ada'],
            ['label' => 'Status Audit Kesiapan', 'value' => $validation['all_passed'] ? 'Siap Tutup Buku' : 'Perlu Tindakan'],
            ['label' => 'Approval Pending', 'value' => $validation['pending_approvals'].' item'],
        ];

        return view('pages.keuangan.closing', [
            'title' => 'Tutup Buku & Penguncian Periode',
            'eyebrow' => 'Financial Closing',
            'description' => 'Tutup buku bulanan, tahunan, dan BOS merekam snapshot saldo kas/bank dan membekukan transaksi.',
            'closings' => $closings,
            'validation' => $validation,
            'accounts' => $accounts,
            'metrics' => $metrics,
        ]);
    }

    public function storeClosing(Request $request, SchoolContext $schoolContext, FinanceClosingService $closing): RedirectResponse
    {
        $schoolId = $this->activeSchoolId($request, $schoolContext);
        $validated = $request->validate([
            'type' => ['required', 'in:monthly,yearly,bos'],
            'period' => ['required', 'date_format:Y-m'],
        ]);

        $closing->close($schoolId, $validated['type'], $validated['period'], $request->user()->id);

        return back()->with('success', 'Periode finance berhasil ditutup dan saldo telah dikunci.');
    }

    public function ledger(Request $request, SchoolContext $schoolContext, FinanceLedgerService $ledger): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        $entries = $ledger->entries($schoolId, [
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'source_funding' => $request->input('source_funding'),
            'account_id' => $request->input('account_id'),
        ]);

        return view('pages.foundation.index', [
            'title' => 'Ledger Finance',
            'eyebrow' => 'Finance Ledger',
            'description' => 'Penerimaan dan pengeluaran yang sudah disetujui pada sekolah aktif.',
            'ledger' => $entries,
            'rows' => $entries,
            'columns' => ['date' => 'Tanggal', 'ref' => 'Referensi', 'description' => 'Keterangan', 'debet' => 'Debet', 'kredit' => 'Kredit'],
        ]);
    }

    public function reports(Request $request, SchoolContext $schoolContext, FinanceLedgerService $ledger): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        $filters = $request->only(['from', 'to', 'source_funding', 'account_id']);

        $summary = $ledger->summary($schoolId);
        $entries = $ledger->entries($schoolId, $filters);
        $accounts = SchoolAccount::where('school_id', $schoolId)->get();

        $debetTotal = (float) $entries->sum('debet');
        $kreditTotal = (float) $entries->sum('kredit');
        $surplusDefisit = $debetTotal - $kreditTotal;

        $metrics = [
            ['label' => 'Total Penerimaan', 'value' => 'Rp '.number_format($debetTotal, 0, ',', '.')],
            ['label' => 'Total Pengeluaran', 'value' => 'Rp '.number_format($kreditTotal, 0, ',', '.')],
            ['label' => 'Surplus / (Defisit)', 'value' => 'Rp '.number_format($surplusDefisit, 0, ',', '.')],
            ['label' => 'Sisa Piutang Siswa', 'value' => 'Rp '.number_format($summary['outstanding'], 0, ',', '.')],
            ['label' => 'Total Saldo Rekening', 'value' => 'Rp '.number_format($summary['account_balance'], 0, ',', '.')],
        ];

        return view('pages.keuangan.reports', [
            'title' => 'Laporan Keuangan & Pembukuan',
            'eyebrow' => 'Finance Reports',
            'description' => 'Ringkasan mutasi kas, realisasi anggaran, dan buku jurnal keuangan sekolah yang telah disetujui.',
            'summary' => $summary,
            'entries' => $entries,
            'accounts' => $accounts,
            'filters' => $filters,
            'metrics' => $metrics,
        ]);
    }

    private function typePage(string $title, string $type, $rows): View
    {
        return view('pages.foundation.index', [
            'title' => $title,
            'eyebrow' => 'Finance Master',
            'description' => $type === 'income' ? 'Master jenis pemasukan untuk komite, BOS, dan sumber lain.' : 'Master jenis pengeluaran, sumber dana, komponen BOS, dan approval.',
            'rows' => $rows,
            'columns' => $type === 'income'
                ? ['code' => 'Kode', 'name' => 'Nama', 'category' => 'Kategori', 'requires_approval' => 'Approval']
                : ['code' => 'Kode', 'name' => 'Nama', 'source_funding' => 'Sumber', 'requires_approval' => 'Approval'],
        ]);
    }

    private function financeMetrics(FinanceLedgerService $ledger, ?int $schoolId): array
    {
        $summary = $ledger->summary($schoolId);

        return [
            ['label' => 'Terkumpul', 'value' => 'Rp '.number_format($summary['collected'], 0, ',', '.')],
            ['label' => 'Tunggakan', 'value' => 'Rp '.number_format($summary['outstanding'], 0, ',', '.')],
            ['label' => 'Saldo Rekening', 'value' => 'Rp '.number_format($summary['account_balance'], 0, ',', '.')],
        ];
    }

    private function activeSchoolId(Request $request, SchoolContext $schoolContext): int
    {
        return $schoolContext->activeSchoolIdFor($request->user());
    }
}
