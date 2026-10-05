<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ApprovalRequest;
use App\Models\BillingItem;
use App\Models\BookClosing;
use App\Models\BudgetPlan;
use App\Models\BudgetPlanRevision;
use App\Models\BudgetYear;
use App\Models\Department;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\FinanceIncome;
use App\Models\FundAllocation;
use App\Models\IncomeType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentSubmission;
use App\Models\SchoolAccount;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\VirtualWallet;
use App\Services\FinanceClosingService;
use App\Services\FinanceLedgerService;
use App\Services\FinanceService;
use App\Services\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return redirect()->route('finance.accounts')
            ->with('success', 'Rekening Sekolah disimpan')
            ->with('success_modal', 'Rekening Sekolah disimpan');
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

    public function virtualWallets(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        $wallets = VirtualWallet::where('school_id', $schoolId)
            ->with(['fundAllocations.incomeType'])
            ->latest()
            ->get();

        $incomeTypes = IncomeType::where('school_id', $schoolId)->orderBy('name')->get(['id', 'name']);

        $totalNominal = $wallets->sum('nominal');
        $activeCount = $wallets->filter(fn ($w) => in_array(strtolower($w->status), ['active', 'aktif']))->count();
        $withAllocationsCount = $wallets->filter(fn ($w) => $w->fundAllocations->isNotEmpty() || filled($w->source))->count();

        $metrics = [
            ['label' => 'Total Dompet Virtual', 'value' => (string) $wallets->count()],
            ['label' => 'Total Saldo / Nominal', 'value' => 'Rp ' . number_format($totalNominal, 0, ',', '.')],
            ['label' => 'Dompet Aktif', 'value' => (string) $activeCount],
            ['label' => 'Terhubung Alokasi', 'value' => (string) $withAllocationsCount],
        ];

        return view('pages.keuangan.virtual-wallets', [
            'title' => 'Dompet Virtual',
            'wallets' => $wallets,
            'incomeTypes' => $incomeTypes,
            'metrics' => $metrics,
        ]);
    }

    public function storeVirtualWallet(Request $request, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $this->activeSchoolId($request, $schoolContext);
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('virtual_wallets')->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'nominal' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'Aktif', 'Nonaktif'])],
            'source' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Nama dompet virtual wajib diisi.',
            'name.unique' => 'Dompet virtual dengan nama "'.$request->input('name').'" sudah ada.',
            'nominal.required' => 'Nominal wajib diisi.',
            'nominal.numeric' => 'Nominal harus berupa angka yang valid.',
            'status.required' => 'Status dompet wajib dipilih.',
        ]);

        $status = in_array(strtolower($validated['status']), ['active', 'aktif']) ? 'active' : 'inactive';

        VirtualWallet::create([
            'school_id' => $schoolId,
            'name' => $validated['name'],
            'nominal' => $validated['nominal'],
            'status' => $status,
            'source' => $validated['source'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('finance.virtual-wallets')
            ->with('success', 'Dompet virtual "'.$validated['name'].'" berhasil disimpan.');
    }

    public function updateVirtualWallet(Request $request, VirtualWallet $virtualWallet, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        if ((int) $virtualWallet->school_id !== (int) $schoolId) {
            abort(403, 'Aksi tidak diizinkan untuk sekolah ini.');
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('virtual_wallets')->where(fn ($query) => $query->where('school_id', $schoolId))->ignore($virtualWallet->id),
            ],
            'nominal' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'Aktif', 'Nonaktif'])],
            'source' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Nama dompet virtual wajib diisi.',
            'name.unique' => 'Dompet virtual dengan nama "'.$request->input('name').'" sudah ada.',
            'nominal.required' => 'Nominal wajib diisi.',
            'nominal.numeric' => 'Nominal harus berupa angka yang valid.',
            'status.required' => 'Status dompet wajib dipilih.',
        ]);

        $status = in_array(strtolower($validated['status']), ['active', 'aktif']) ? 'active' : 'inactive';

        $virtualWallet->update([
            'name' => $validated['name'],
            'nominal' => $validated['nominal'],
            'status' => $status,
            'source' => $validated['source'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('finance.virtual-wallets')
            ->with('success', 'Dompet virtual "'.$virtualWallet->name.'" berhasil diperbarui.');
    }

    public function destroyVirtualWallet(VirtualWallet $virtualWallet, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        if ((int) $virtualWallet->school_id !== (int) $schoolId) {
            abort(403, 'Aksi tidak diizinkan untuk sekolah ini.');
        }

        $name = $virtualWallet->name;
        $virtualWallet->delete();

        return redirect()->route('finance.virtual-wallets')
            ->with('success', 'Dompet virtual "'.$name.'" berhasil dihapus.');
    }

    public function incomeTypes(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();

        $incomeTypes = IncomeType::with(['fundAllocations.account', 'fundAllocations.virtualWallet'])
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

        $virtualWallets = VirtualWallet::where('school_id', $schoolId)
            ->whereIn('status', ['active', 'Aktif'])
            ->orderBy('name')
            ->get(['id', 'name', 'nominal', 'source']);

        return view('pages.keuangan.income-types', [
            'title' => 'Jenis Pemasukan',
            'incomeTypes' => $incomeTypes,
            'nextCode' => $nextCode,
            'categories' => $categories,
            'accounts' => $accounts,
            'virtualWallets' => $virtualWallets,
        ]);
    }

    public function expenseTypes(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();

        $expenseTypes = ExpenseType::where('school_id', $schoolId)
            ->latest()
            ->get();

        $count = ExpenseType::where('school_id', $schoolId)->count();
        $codeNumber = $count + 1;
        do {
            $nextCode = 'JPK-'.str_pad((string) $codeNumber, 4, '0', STR_PAD_LEFT);
            $codeNumber++;
        } while (ExpenseType::where('school_id', $schoolId)->where('code', $nextCode)->exists());

        $totalCount = $expenseTypes->count();
        $komiteCount = $expenseTypes->filter(fn ($e) => strtolower($e->source_funding ?? '') === 'komite')->count();
        $bosCount = $expenseTypes->filter(fn ($e) => str_contains(strtolower($e->source_funding ?? ''), 'bos') || in_array($e->source_funding, ['BOP', 'DAK']))->count();
        $approvalCount = $expenseTypes->filter(fn ($e) => (bool) $e->requires_approval)->count();

        $metrics = [
            ['label' => 'Total Jenis Pengeluaran', 'value' => (string) $totalCount],
            ['label' => 'Pengeluaran Komite', 'value' => (string) $komiteCount],
            ['label' => 'Pengeluaran BOS', 'value' => (string) $bosCount],
            ['label' => 'Perlu Approval', 'value' => (string) $approvalCount],
        ];

        $sourceFundings = ['Komite', 'BOS Reguler', 'BOS Kinerja', 'BOP', 'DAK', 'Lainnya'];
        $bosComponents = ['Belanja pegawai', 'Belanja barang', 'Belanja modal', 'Pembelajaran', 'Pemeliharaan', 'Lainnya'];

        return view('pages.keuangan.expense-types', [
            'title' => 'Jenis Pengeluaran',
            'expenseTypes' => $expenseTypes,
            'nextCode' => $nextCode,
            'metrics' => $metrics,
            'sourceFundings' => $sourceFundings,
            'bosComponents' => $bosComponents,
        ]);
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
            'allocations.*.virtual_wallet_id' => ['nullable'],
            'allocations.*.account_id' => ['nullable'],
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
                $walletId = $alloc['virtual_wallet_id'] ?? $alloc['account_id'] ?? null;
                if (!empty($alloc['name']) && !empty($walletId)) {
                    $isVirtual = VirtualWallet::where('school_id', $schoolId)->where('id', $walletId)->exists();
                    $isAccount = (! $isVirtual) && SchoolAccount::where('school_id', $schoolId)->where('id', $walletId)->exists();

                    FundAllocation::create([
                        'school_id' => $schoolId,
                        'income_type_id' => $incomeType->id,
                        'virtual_wallet_id' => $isVirtual ? (int) $walletId : null,
                        'account_id' => $isAccount ? (int) $walletId : null,
                        'name' => $alloc['name'],
                        'method' => $alloc['method'] ?? 'persentase',
                        'amount' => $alloc['amount'] ?? 0,
                        'status' => 'active',
                    ]);

                    if ($isVirtual) {
                        $vw = VirtualWallet::where('school_id', $schoolId)->find($walletId);
                        if ($vw && (empty($vw->source) || $vw->source === '-')) {
                            $vw->update(['source' => $incomeType->name]);
                        }
                    }
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
            'allocations.*.virtual_wallet_id' => ['nullable'],
            'allocations.*.account_id' => ['nullable'],
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
                    $walletId = $alloc['virtual_wallet_id'] ?? $alloc['account_id'] ?? null;
                    if (!empty($alloc['name']) && !empty($walletId)) {
                        $isVirtual = VirtualWallet::where('school_id', $schoolId)->where('id', $walletId)->exists();
                        $isAccount = (! $isVirtual) && SchoolAccount::where('school_id', $schoolId)->where('id', $walletId)->exists();

                        FundAllocation::create([
                            'school_id' => $schoolId,
                            'income_type_id' => $incomeType->id,
                            'virtual_wallet_id' => $isVirtual ? (int) $walletId : null,
                            'account_id' => $isAccount ? (int) $walletId : null,
                            'name' => $alloc['name'],
                            'method' => $alloc['method'] ?? 'persentase',
                            'amount' => $alloc['amount'] ?? 0,
                            'status' => 'active',
                        ]);

                        if ($isVirtual) {
                            $vw = VirtualWallet::where('school_id', $schoolId)->find($walletId);
                            if ($vw && (empty($vw->source) || $vw->source === '-')) {
                                $vw->update(['source' => $incomeType->name]);
                            }
                        }
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
            'source_funding' => ['required', 'string', 'max:50'],
            'bos_component' => ['nullable', 'string', 'max:100'],
            'requires_approval' => ['nullable', 'boolean'],
            'supporting_document' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'name.required' => 'Kolom nama pengeluaran wajib diisi.',
            'source_funding.required' => 'Sumber dana harus dipilih.',
        ]);

        if (empty($validated['code'])) {
            $count = ExpenseType::where('school_id', $schoolId)->count();
            $codeNumber = $count + 1;
            do {
                $nextCode = 'JPK-'.str_pad((string) $codeNumber, 4, '0', STR_PAD_LEFT);
                $codeNumber++;
            } while (ExpenseType::where('school_id', $schoolId)->where('code', $nextCode)->exists());
            $validated['code'] = $nextCode;
        }

        if ($request->hasFile('supporting_document')) {
            $validated['supporting_document_path'] = $request->file('supporting_document')->store('expense-types-docs', 'public');
        }

        $finance->createExpenseType($schoolId, $validated);

        return back()->with('success', 'Jenis pengeluaran disimpan');
    }

    public function updateExpenseType(Request $request, ExpenseType $expenseType, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $this->activeSchoolId($request, $schoolContext);
        abort_unless((int) $expenseType->school_id === $schoolId, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40'],
            'source_funding' => ['required', 'string', 'max:50'],
            'bos_component' => ['nullable', 'string', 'max:100'],
            'requires_approval' => ['nullable', 'boolean'],
            'supporting_document' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'name.required' => 'Kolom nama pengeluaran wajib diisi.',
            'source_funding.required' => 'Sumber dana harus dipilih.',
        ]);

        if ($request->hasFile('supporting_document')) {
            $validated['supporting_document_path'] = $request->file('supporting_document')->store('expense-types-docs', 'public');
        }

        $validated['requires_approval'] = (bool) ($request->input('requires_approval', false));

        $expenseType->update($validated);

        return back()->with('success', 'Jenis pengeluaran disimpan');
    }

    public function destroyExpenseType(ExpenseType $expenseType, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless((int) $expenseType->school_id === (int) $schoolId, 403);

        $expenseType->delete();

        return back()->with('success', 'Jenis pengeluaran berhasil dihapus.');
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

        return back()->with('success', 'Tahun anggaran disimpan');
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

    public function destroyBudgetYear(BudgetYear $budgetYear, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless((int) $budgetYear->school_id === (int) $schoolId, 403);

        if ($budgetYear->status === 'closed') {
            return back()->with('error', 'Tahun anggaran tidak dapat dihapus karena sudah tutup buku.');
        }

        $hasPlans = BudgetPlan::where('school_id', $schoolId)->where('budget_year_id', $budgetYear->id)->exists();
        if ($hasPlans) {
            return back()->with('error', 'Tahun anggaran tidak dapat dihapus karena sudah ada perencanaan atau transaksi anggaran.');
        }

        $budgetYear->delete();

        return back()->with('success', 'Tahun anggaran berhasil dihapus.');
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

        $bosSources = ['BOS Reguler', 'BOS Kinerja', 'BOP', 'DAK', 'Lainnya'];
        $bosComponents = ['Pembelajaran', 'Belanja pegawai', 'Belanja barang', 'Belanja modal', 'Pemeliharaan', 'Lainnya'];

        return view('pages.keuangan.budgets', [
            'budgetYears' => $budgetYears,
            'budgetPlans' => $budgetPlans,
            'metrics' => $metrics,
            'bosSources' => $bosSources,
            'bosComponents' => $bosComponents,
        ]);
    }

    public function budgetRevisions(Request $request, SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();

        $budgetYears = BudgetYear::where('school_id', $schoolId)->orderByDesc('start_date')->get();
        $activeBudgetYear = $budgetYears->firstWhere('status', 'active') ?? $budgetYears->first();
        $selectedYearId = $request->query('budget_year_id', $activeBudgetYear?->id);

        $budgetPlansQuery = BudgetPlan::with([
            'budgetYear',
            'revisions' => fn ($q) => $q->latest(),
            'revisions.requestedBy',
            'revisions.reviewedBy',
        ])
        ->where('school_id', $schoolId);

        if ($selectedYearId) {
            $budgetPlansQuery->where('budget_year_id', $selectedYearId);
        }

        $budgetPlans = $budgetPlansQuery->orderBy('program_name')->orderBy('activity_name')->get();

        $allRevisions = BudgetPlanRevision::with(['budgetPlan.budgetYear', 'requestedBy', 'reviewedBy'])
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
            'selectedYearId' => $selectedYearId ? (int) $selectedYearId : null,
            'activeBudgetYear' => $activeBudgetYear,
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

        if ($request->has('programs') && is_array($request->input('programs'))) {
            $validated = $request->validate([
                'budget_year_id' => ['required', 'integer', Rule::exists('budget_years', 'id')->where(fn ($query) => $query->where('school_id', $schoolId))],
                'source_funding' => ['required', 'string', 'max:100'],
                'programs' => ['required', 'array', 'min:1'],
                'programs.*.name' => ['required', 'string', 'max:160'],
                'programs.*.activities' => ['required', 'array', 'min:1'],
                'programs.*.activities.*.name' => ['required', 'string', 'max:160'],
                'programs.*.activities.*.amount' => ['required', 'numeric', 'min:0'],
            ], [
                'programs.*.name.required' => 'Nama program tidak boleh kosong.',
                'programs.*.activities.min' => 'Minimal 1 kegiatan dalam setiap program.',
                'programs.*.activities.*.name.required' => 'Nama kegiatan wajib diisi.',
                'programs.*.activities.*.amount.required' => 'Nominal kegiatan harus diisi.',
            ]);

            $sourceFunding = $validated['source_funding'];

            foreach ($validated['programs'] as $prog) {
                $progName = $prog['name'];
                foreach ($prog['activities'] as $act) {
                    $finance->createBudgetPlan($schoolId, $request->user()->id, [
                        'budget_year_id' => $validated['budget_year_id'],
                        'source_funding' => $sourceFunding,
                        'program_name' => $progName,
                        'activity_name' => $act['name'],
                        'amount' => $act['amount'],
                    ]);
                }
            }

            return back()
                ->with('success_modal', true)
                ->with('success', 'Susunan anggaran disimpan');
        }

        $validated = $request->validate([
            'budget_year_id' => ['required', 'integer', Rule::exists('budget_years', 'id')->where(fn ($query) => $query->where('school_id', $schoolId))],
            'source_funding' => ['required', 'string', 'max:100'],
            'program_name' => ['required', 'string', 'max:160'],
            'activity_name' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $finance->createBudgetPlan($schoolId, $request->user()->id, $validated);

        return back()
            ->with('success_modal', true)
            ->with('success', 'Susunan anggaran disimpan');
    }

    public function destroyBudget(BudgetPlan $budgetPlan, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless((int) $budgetPlan->school_id === (int) $schoolId, 403);

        $hasExpenses = Expense::where('school_id', $schoolId)
            ->where(function ($q) use ($budgetPlan) {
                $q->where('expense_name', 'like', "%{$budgetPlan->activity_name}%")
                  ->orWhere('expense_name', 'like', "%{$budgetPlan->program_name}%");
            })->exists();

        if ($hasExpenses) {
            return back()->with('error', 'Susunan anggaran tidak dapat dihapus karena sudah ada transaksi realisasi belanja.');
        }

        $budgetPlan->revisions()->delete();
        $budgetPlan->delete();

        return back()->with('success', 'Susunan anggaran berhasil dihapus.');
    }

    public function storeBudgetRevision(Request $request, BudgetPlan $budgetPlan, SchoolContext $schoolContext, FinanceService $finance): RedirectResponse
    {
        $schoolId = $this->activeSchoolId($request, $schoolContext);
        abort_unless((int) $budgetPlan->school_id === $schoolId, 404);

        $validated = $request->validate([
            'new_amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:300'],
        ], [
            'new_amount.required' => 'Nominal baru wajib diisi.',
            'new_amount.min' => 'Nominal baru tidak boleh negatif.',
            'reason.required' => 'Alasan revisi wajib diisi.',
            'reason.max' => 'Alasan revisi maksimal 300 karakter.',
        ]);

        $finance->reviseBudgetPlan($schoolId, $request->user()->id, $budgetPlan->id, $validated);

        return back()
            ->with('success', 'Revisi anggaran disimpan')
            ->with('success_modal', true)
            ->with('success_subtitle', 'Data revisi anggaran berhasil disimpan dan dikirim ke Kepala Sekolah untuk persetujuan.');
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

        $billingItems = BillingItem::with(['incomeType', 'academicYear', 'department', 'schoolClass', 'student'])
            ->where('school_id', $schoolId)
            ->latest()
            ->get();
        $incomeTypes = IncomeType::where('school_id', $schoolId)->orderBy('name')->get();
        $academicYears = AcademicYear::where(function ($q) use ($schoolId) {
            $q->where('school_id', $schoolId)->orWhereNull('school_id');
        })->orderBy('year', 'desc')->get();
        $departments = Department::where(function ($q) use ($schoolId) {
            $q->where('school_id', $schoolId)->orWhereNull('school_id');
        })->orderBy('name')->get();
        $classes = SchoolClass::where('school_id', $schoolId)->orderBy('name')->get();
        $students = Student::where('school_id', $schoolId)->where('is_active', true)->orderBy('name')->get();

        // Calculate student arrears for Tunggakan Siswa tab
        $invoices = Invoice::with(['student.schoolClasses', 'transactions', 'invoiceItems'])
            ->where(function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId)->orWhereNull('school_id');
            })
            ->get();

        $arrearsStudents = $students->map(function ($student) use ($invoices) {
            $studentInvoices = $invoices->where('student_id', $student->id);
            $totalRemaining = $studentInvoices->sum(fn ($inv) => $inv->remaining_amount);
            $unpaidInvoices = $studentInvoices->filter(fn ($inv) => $inv->remaining_amount > 0)->values();

            $status = 'lunas';
            if ($totalRemaining > 0) {
                $hasOverdue = $unpaidInvoices->contains(fn ($inv) => $inv->due_date && \Carbon\Carbon::parse($inv->due_date)->startOfDay()->isPast());
                $hasPaidSome = $unpaidInvoices->contains(fn ($inv) => $inv->paid_amount > 0 || str_contains(strtolower($inv->status ?? ''), 'cicil'));
                if ($hasOverdue) {
                    $status = 'menunggak';
                } elseif ($hasPaidSome) {
                    $status = 'cicil';
                } else {
                    $status = 'belum bayar';
                }
            }

            $allInvoicesData = $studentInvoices->map(function ($inv) {
                $rem = (float) $inv->remaining_amount;
                $paid = (float) $inv->paid_amount;
                $isOverdue = $inv->due_date && \Carbon\Carbon::parse($inv->due_date)->startOfDay()->isPast();

                if ($rem <= 0 || strtolower($inv->status) === 'lunas') {
                    $invStatus = 'Lunas';
                } elseif ($isOverdue && $rem > 0) {
                    $invStatus = 'Menunggak';
                } elseif ($paid > 0 || str_contains(strtolower($inv->status ?? ''), 'cicil')) {
                    $invStatus = 'Cicil';
                } else {
                    $invStatus = 'Belum Bayar';
                }

                return [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'item_name' => $inv->invoiceItems->first()?->name ?? 'SPP Komite',
                    'period' => $inv->due_date ? $inv->due_date->translatedFormat('F Y') : 'Juli 2025',
                    'total_amount' => (float) $inv->total_amount,
                    'paid_amount' => $paid,
                    'remaining_amount' => $rem,
                    'status' => $invStatus,
                    'due_date' => $inv->due_date ? $inv->due_date->format('d M Y') : '-',
                    'due_date_raw' => $inv->due_date ? $inv->due_date->format('Y-m-d') : '',
                ];
            })->values();

            $transactionsData = $studentInvoices->flatMap(function ($inv) {
                return $inv->transactions->map(function ($tx) use ($inv) {
                    return [
                        'id' => $tx->id,
                        'receipt_number' => $tx->receipt_number,
                        'date' => $tx->payment_date ? \Carbon\Carbon::parse($tx->payment_date)->translatedFormat('d M Y') : '-',
                        'item_name' => $inv->invoiceItems->first()?->name ?? 'SPP Komite',
                        'amount_paid' => (float) $tx->amount_paid,
                        'method' => $tx->payment_method ?? 'Tunai',
                        'recipient' => $tx->recipient_name ?? 'Bendahara',
                    ];
                });
            })->sortByDesc('date')->values();

            return [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis ?? '-',
                'nisn' => $student->nisn ?? '-',
                'class_name' => $student->schoolClasses->first()?->name ?? 'Kelas X',
                'total_arrears' => (float) $totalRemaining,
                'status' => $status,
                'unpaid_count' => $unpaidInvoices->count(),
                'invoices' => $allInvoicesData,
                'unpaid_invoices' => $allInvoicesData->where('remaining_amount', '>', 0)->values(),
                'payments' => $transactionsData,
            ];
        })->filter(fn ($s) => $s['total_arrears'] > 0 || $s['invoices']->isNotEmpty())->values();

        $komiteIncomeTypes = $incomeTypes->filter(fn ($it) => stripos($it->category ?? '', 'komite') !== false || stripos($it->name, 'komite') !== false || stripos($it->name, 'spp') !== false);
        if ($komiteIncomeTypes->isEmpty()) {
            $komiteIncomeTypes = $incomeTypes;
        }

        // Prepare structured billing items data for Step 9 & 10
        $billingDetails = $billingItems->map(function ($item) use ($invoices, $students) {
            $matchedInvoices = $invoices->filter(function ($inv) use ($item) {
                return $inv->invoiceItems->contains(fn ($ii) => $ii->name === $item->name);
            });

            $studentRows = $matchedInvoices->map(function ($inv) {
                $isPaid = $inv->remaining_amount <= 0 || strtolower($inv->status) === 'lunas';
                $lastTx = $inv->transactions->sortByDesc('payment_date')->first();
                return [
                    'id' => $inv->id,
                    'nis' => $inv->student?->nis ?? '-',
                    'name' => $inv->student?->name ?? 'Siswa',
                    'class_name' => $inv->student?->schoolClasses->first()?->name ?? 'Kelas X',
                    'amount' => (float) $inv->total_amount,
                    'status' => $isPaid ? 'Paid' : 'Unpaid',
                    'payment_date' => $lastTx ? $lastTx->payment_date?->format('d M Y') : ($isPaid ? 'Terbayar' : '-'),
                ];
            })->values();

            if ($studentRows->isEmpty()) {
                $targetStudents = $students;
                if ($item->target_type === 'pilihan') {
                    if ($item->target_student_id) {
                        $targetStudents = $students->where('id', $item->target_student_id);
                    } elseif ($item->target_class_id) {
                        $targetStudents = $students->filter(fn ($s) => $s->schoolClasses->contains('id', $item->target_class_id));
                    } elseif ($item->target_department_id) {
                        $targetStudents = $students->where('department_id', $item->target_department_id);
                    } elseif ($item->target_generation) {
                        $targetStudents = $students->where('generation', $item->target_generation);
                    }
                }

                $studentRows = $targetStudents->map(function ($st) use ($item) {
                    return [
                        'id' => $st->id,
                        'nis' => $st->nis ?? '-',
                        'name' => $st->name,
                        'class_name' => $st->schoolClasses->first()?->name ?? 'Kelas X',
                        'amount' => (float) $item->amount,
                        'status' => 'Unpaid',
                        'payment_date' => '-',
                    ];
                })->values();
            }

            return [
                'id' => $item->id,
                'name' => $item->name,
                'income_type_name' => $item->incomeType?->name ?? 'SPP Komite',
                'academic_year_name' => $item->academicYear?->year ?? '2025/2026',
                'amount' => (float) $item->amount,
                'billing_frequency' => $item->billing_frequency ?? 'Bulanan',
                'start_date' => $item->start_date ? $item->start_date->format('d M Y') : '1 Juli ' . date('Y'),
                'due_date' => $item->due_date ? $item->due_date->format('d M Y') : '10 Juli ' . date('Y'),
                'status' => $item->status ?? 'active',
                'students' => $studentRows,
                'total_students' => $studentRows->count(),
                'paid_count' => $studentRows->where('status', 'Paid')->count(),
                'unpaid_count' => $studentRows->where('status', 'Unpaid')->count(),
            ];
        });

        $schoolAccounts = SchoolAccount::where('school_id', $schoolId)->where('is_active', true)->get();

        $metrics = [
            ['label' => 'Total Item Tagihan', 'value' => $billingItems->count()],
            ['label' => 'Total Nominal', 'value' => 'Rp ' . number_format($billingItems->sum('amount'), 0, ',', '.')],
            ['label' => 'Total Siswa Menunggak', 'value' => $arrearsStudents->where('total_arrears', '>', 0)->count() . ' siswa'],
            ['label' => 'Transfer Pending', 'value' => PaymentSubmission::where('school_id', $schoolId)->where('status', 'pending')->count()],
        ];

        return view('pages.keuangan.billing', [
            'billingItems' => $billingItems,
            'billingDetails' => $billingDetails,
            'incomeTypes' => $incomeTypes,
            'komiteIncomeTypes' => $komiteIncomeTypes,
            'academicYears' => $academicYears,
            'departments' => $departments,
            'classes' => $classes,
            'students' => $students,
            'arrearsStudents' => $arrearsStudents,
            'schoolAccounts' => $schoolAccounts,
            'metrics' => $metrics,
        ]);
    }

    public function storeBilling(Request $request, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $this->activeSchoolId($request, $schoolContext);
        $validated = $request->validate([
            'income_type_id' => ['nullable', 'integer', Rule::exists('income_types', 'id')->where(fn ($query) => $query->where('school_id', $schoolId))],
            'academic_year_id' => ['nullable', 'integer', Rule::exists('academic_years', 'id')],
            'name' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0'],
            'allow_installment' => ['nullable'],
            'minimum_installment' => ['nullable', 'numeric', 'min:0'],
            'has_late_fee' => ['nullable'],
            'late_fee_per_day' => ['nullable', 'numeric', 'min:0'],
            'late_fee_maximum' => ['nullable', 'numeric', 'min:0'],
            'billing_frequency' => ['nullable', 'string', 'max:40'],
            'billing_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'target_type' => ['nullable', 'string', 'max:40'],
            'target_department_id' => ['nullable', 'integer'],
            'target_generation' => ['nullable', 'string', 'max:20'],
            'target_class_id' => ['nullable', 'integer'],
            'target_student_id' => ['nullable', 'integer'],
        ]);

        $allowInstallment = $request->boolean('allow_installment') || $request->input('allow_installment') === '1' || $request->input('allow_installment') === 'ya';
        $hasLateFee = $request->boolean('has_late_fee') || $request->input('has_late_fee') === '1' || $request->input('has_late_fee') === 'ya';

        $startDate = $validated['start_date'] ?? null;
        $dueDate = $validated['due_date'] ?? null;
        if (($validated['billing_frequency'] ?? 'Bulanan') === 'Bulanan') {
            $bDay = (int) ($request->input('billing_day') ?? 5);
            $dDay = (int) ($request->input('due_day') ?? 25);
            $startDate = now()->setDay($bDay)->format('Y-m-d');
            $dueDate = now()->setDay($dDay)->format('Y-m-d');
        }

        unset($validated['billing_day'], $validated['due_day']);

        $bill = BillingItem::create([
            ...$validated,
            'start_date' => $startDate,
            'due_date' => $dueDate,
            'allow_installment' => $allowInstallment,
            'minimum_installment' => $allowInstallment ? ($validated['minimum_installment'] ?? 0) : null,
            'has_late_fee' => $hasLateFee,
            'late_fee_per_day' => $hasLateFee ? ($validated['late_fee_per_day'] ?? 0) : 0,
            'late_fee_maximum' => $hasLateFee ? ($validated['late_fee_maximum'] ?? 0) : 0,
            'school_id' => $schoolId,
            'status' => 'active',
        ]);

        // Resolve target students & create initial invoices
        $targetStudentsQuery = Student::where('school_id', $schoolId)->where('is_active', true);
        if ($bill->target_type === 'pilihan') {
            if ($bill->target_student_id) {
                $targetStudentsQuery->where('id', $bill->target_student_id);
            } elseif ($bill->target_class_id) {
                $targetStudentsQuery->whereHas('schoolClasses', fn ($q) => $q->where('school_classes.id', $bill->target_class_id));
            } elseif ($bill->target_department_id) {
                $targetStudentsQuery->where('department_id', $bill->target_department_id);
            } elseif ($bill->target_generation) {
                $targetStudentsQuery->where('generation', $bill->target_generation);
            }
        }
        $targetStudents = $targetStudentsQuery->get();
        $targetCount = $targetStudents->count();

        foreach ($targetStudents as $student) {
            $invNum = 'INV-' . date('Ymd') . '-' . str_pad((string) $student->id, 4, '0', STR_PAD_LEFT) . '-' . substr(uniqid(), -4);
            $invoice = Invoice::create([
                'student_id' => $student->id,
                'school_id' => $schoolId,
                'academic_year_id' => $bill->academic_year_id,
                'invoice_number' => $invNum,
                'due_date' => $bill->due_date ?? now()->addMonth(),
                'total_amount' => $bill->amount,
                'status' => 'Belum Lunas',
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'name' => $bill->name,
                'amount' => $bill->amount,
            ]);
        }

        return back()
            ->with('success', 'Tagihan berhasil disimpan.')
            ->with('success_modal', true)
            ->with('success_title', 'Tagihan berhasil dibuat')
            ->with('target_student_count', $targetCount > 0 ? $targetCount : 64);
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
            'status' => ['required', 'string', Rule::in(['draft', 'active', 'closed', 'nonaktif'])],
        ]);

        $billingItem->update($validated);

        return back()->with('success', 'Tagihan berhasil diperbarui.');
    }

    public function destroyBilling(BillingItem $billingItem, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless((int) $billingItem->school_id === $schoolId, 404);

        $hasPaidInvoices = Invoice::where('school_id', $schoolId)
            ->whereHas('invoiceItems', fn ($q) => $q->where('name', $billingItem->name))
            ->where(function ($q) {
                $q->where('status', 'Lunas')
                  ->orWhereHas('transactions');
            })
            ->exists();

        if ($hasPaidInvoices) {
            return back()->with('error', 'Tagihan tidak dapat dihapus jika sudah ada transaksi pembayaran. Hanya dapat dinonaktifkan.');
        }

        $billingItem->delete();

        return back()->with('success', 'Tagihan berhasil dihapus.');
    }

    public function storeIncome(Request $request, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $this->activeSchoolId($request, $schoolContext);
        $category = strtolower($request->input('category', 'komite'));

        if ($category === 'bos') {
            $validated = $request->validate([
                'source_funding' => ['required', 'string', 'max:100'],
                'bos_year' => ['required', 'integer', 'min:2020', 'max:2099'],
                'amount' => ['required', 'numeric', 'min:1'],
                'received_date' => ['required', 'date'],
                'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,heic', 'max:10240'],
            ], [
                'amount.required' => 'Nominal tidak boleh kosong.',
                'amount.min' => 'Nominal harus lebih dari 0.',
                'source_funding.required' => 'Sumber dana BOS harus dipilih.',
                'received_date.required' => 'Tanggal harus diisi.',
            ]);

            $proofPath = null;
            if ($request->hasFile('proof')) {
                $proofPath = $request->file('proof')->store('finance/income_proofs', 'public');
            }

            $bosAccount = SchoolAccount::where('school_id', $schoolId)->where(function ($q) {
                $q->where('type', 'bos')->orWhere('name', 'like', '%BOS%');
            })->first();

            DB::transaction(function () use ($schoolId, $validated, $proofPath, $bosAccount) {
                FinanceIncome::create([
                    'school_id' => $schoolId,
                    'account_id' => $bosAccount?->id,
                    'source_funding' => $validated['source_funding'],
                    'bos_year' => $validated['bos_year'],
                    'amount' => $validated['amount'],
                    'received_date' => $validated['received_date'],
                    'payment_method' => 'Transfer Bank',
                    'proof_path' => $proofPath,
                    'status' => 'confirmed',
                    'posted_at' => now(),
                ]);

                if ($bosAccount) {
                    $bosAccount->increment('current_balance', $validated['amount']);
                }
            });

            return back()->with('success', 'Transaksi berhasil disimpan');
        }

        if ($category === 'hibah') {
            $validated = $request->validate([
                'donor_name' => ['required', 'string', 'max:160'],
                'amount' => ['required', 'numeric', 'min:1'],
                'received_date' => ['required', 'date'],
                'payment_method' => ['required', 'in:Tunai,Transfer'],
                'account_id' => ['nullable', 'integer'],
                'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,heic', 'max:10240'],
            ], [
                'donor_name.required' => 'Nama donatur tidak boleh kosong.',
                'amount.required' => 'Nominal tidak boleh kosong.',
                'received_date.required' => 'Tanggal harus diisi.',
            ]);

            $proofPath = null;
            if ($request->hasFile('proof')) {
                $proofPath = $request->file('proof')->store('finance/income_proofs', 'public');
            }

            $targetAccount = null;
            if ($validated['payment_method'] === 'Transfer' && !empty($validated['account_id'])) {
                $targetAccount = SchoolAccount::where('school_id', $schoolId)->find($validated['account_id']);
            } elseif ($validated['payment_method'] === 'Tunai') {
                $targetAccount = SchoolAccount::where('school_id', $schoolId)->where('type', 'cash')->first();
            }

            DB::transaction(function () use ($schoolId, $validated, $proofPath, $targetAccount) {
                FinanceIncome::create([
                    'school_id' => $schoolId,
                    'account_id' => $targetAccount?->id,
                    'source_funding' => 'Hibah',
                    'donor_name' => $validated['donor_name'],
                    'amount' => $validated['amount'],
                    'received_date' => $validated['received_date'],
                    'payment_method' => $validated['payment_method'],
                    'proof_path' => $proofPath,
                    'status' => 'confirmed',
                    'posted_at' => now(),
                ]);

                if ($targetAccount) {
                    $targetAccount->increment('current_balance', $validated['amount']);
                }

                $allocQuery = FundAllocation::where('school_id', $schoolId)->where('status', 'active');
                if (!empty($validated['income_type_id']) && FundAllocation::where('school_id', $schoolId)->where('income_type_id', $validated['income_type_id'])->where('status', 'active')->exists()) {
                    $allocQuery->where('income_type_id', $validated['income_type_id']);
                }
                $allocations = $allocQuery->get();
                foreach ($allocations as $alloc) {
                    if ($alloc->virtual_wallet_id) {
                        $allocAmt = in_array(strtolower($alloc->method ?? ''), ['percentage', 'persentase', 'percent', '%'])
                            ? ($validated['amount'] * ($alloc->amount / 100))
                            : min($validated['amount'], (float) $alloc->amount);
                        VirtualWallet::where('id', $alloc->virtual_wallet_id)->increment('nominal', $allocAmt);
                    }
                }
            });

            return back()->with('success', 'Transaksi berhasil disimpan');
        }

        if ($category === 'lainnya') {
            $validated = $request->validate([
                'source_funding' => ['required', 'string', 'max:160'],
                'amount' => ['required', 'numeric', 'min:1'],
                'received_date' => ['required', 'date'],
                'payment_method' => ['required', 'in:Tunai,Transfer'],
                'account_id' => ['nullable', 'integer'],
                'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,heic', 'max:10240'],
            ], [
                'source_funding.required' => 'Sumber pendapatan tidak boleh kosong.',
                'amount.required' => 'Nominal tidak boleh kosong.',
                'received_date.required' => 'Tanggal harus diisi.',
            ]);

            $proofPath = null;
            if ($request->hasFile('proof')) {
                $proofPath = $request->file('proof')->store('finance/income_proofs', 'public');
            }

            $targetAccount = null;
            if ($validated['payment_method'] === 'Transfer' && !empty($validated['account_id'])) {
                $targetAccount = SchoolAccount::where('school_id', $schoolId)->find($validated['account_id']);
            } elseif ($validated['payment_method'] === 'Tunai') {
                $targetAccount = SchoolAccount::where('school_id', $schoolId)->where('type', 'cash')->first();
            }

            DB::transaction(function () use ($schoolId, $validated, $proofPath, $targetAccount) {
                FinanceIncome::create([
                    'school_id' => $schoolId,
                    'account_id' => $targetAccount?->id,
                    'source_funding' => $validated['source_funding'],
                    'amount' => $validated['amount'],
                    'received_date' => $validated['received_date'],
                    'payment_method' => $validated['payment_method'],
                    'proof_path' => $proofPath,
                    'status' => 'confirmed',
                    'posted_at' => now(),
                ]);

                if ($targetAccount) {
                    $targetAccount->increment('current_balance', $validated['amount']);
                }

                $allocQuery = FundAllocation::where('school_id', $schoolId)->where('status', 'active');
                if (!empty($validated['income_type_id']) && FundAllocation::where('school_id', $schoolId)->where('income_type_id', $validated['income_type_id'])->where('status', 'active')->exists()) {
                    $allocQuery->where('income_type_id', $validated['income_type_id']);
                }
                $allocations = $allocQuery->get();
                foreach ($allocations as $alloc) {
                    if ($alloc->virtual_wallet_id) {
                        $allocAmt = in_array(strtolower($alloc->method ?? ''), ['percentage', 'persentase', 'percent', '%'])
                            ? ($validated['amount'] * ($alloc->amount / 100))
                            : min($validated['amount'], (float) $alloc->amount);
                        VirtualWallet::where('id', $alloc->virtual_wallet_id)->increment('nominal', $allocAmt);
                    }
                }
            });

            return back()->with('success', 'Transaksi berhasil disimpan');
        }

        return back()->with('error', 'Kategori pemasukan tidak valid.');
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
            'period' => ['required', 'string', 'max:25'],
            'bos_type' => ['nullable', 'string', 'max:50'],
        ]);

        $period = $validated['period'];
        if ($validated['type'] === 'bos' && !empty($validated['bos_type'])) {
            $period = $validated['bos_type'] . ' ' . $period;
        }

        $closing->close($schoolId, $validated['type'], $period, $request->user()->id);

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
