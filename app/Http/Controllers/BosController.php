<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ApprovalRequest;
use App\Models\BudgetCategory;
use App\Models\BudgetPlan;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\IncomeType;
use App\Models\SchoolAccount;
use App\Models\Transaction;
use App\Models\VirtualWallet;
use App\Services\FinanceLedgerService;
use App\Services\ExpensePostingService;
use App\Services\FinanceService;
use App\Services\SchoolContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BosController extends Controller
{
    /**
     * Display BOS Master Data page according to Flow Master BOS.
     */
    public function anggaran(SchoolContext $schoolContext)
    {
        $schoolId = $schoolContext->activeSchoolId() ?? session('active_school_id');

        $categories = BudgetCategory::query()
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->orderBy('id', 'asc')
            ->get();

        // Calculate dynamic receipt metrics
        $bosIncomes = DB::table('finance_incomes')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->where(function ($q) {
                $q->where('source_funding', 'like', '%BOS%')
                  ->orWhere('source_funding', 'like', '%BOP%')
                  ->orWhere('source_funding', 'like', '%DAK%');
            })
            ->sum('amount');

        $totalIncomes = DB::table('finance_incomes')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->sum('amount');


        $metrics = [
            [
                'label' => 'Total Penerimaan',
                'value' => 'Rp ' . number_format($totalIncomes, 0, ',', '.'),
                'sub' => 'Penerimaan tercatat',
            ],
            [
                'label' => 'Dana BOS Diterima',
                'value' => 'Rp ' . number_format($bosIncomes, 0, ',', '.'),
                'sub' => 'Penerimaan BOS tercatat',
            ],
            [
                'label' => 'Komponen Aktif',
                'value' => (string) $categories->where('is_active', true)->count(),
                'sub' => 'Komponen BOS',
            ],
            [
                'label' => 'Sumber Dana',
                'value' => (string) max(1, $categories->pluck('source_funding')->unique()->count()),
                'sub' => 'Kategori Pendanaan',
            ],
        ];

        // 1. System Menampilkan sumber dana (pilihan yang sudah ada: BOS Reguler, BOS Kinerja, BOP, DAK, Lainnya bisa ditambah)
        $defaultSources = ['BOS Reguler', 'BOS Kinerja', 'BOP', 'DAK'];
        $existingSources = $categories->pluck('source_funding')->filter()->unique()->values()->all();
        $sources = array_values(array_unique(array_merge($defaultSources, $existingSources)));

        // 2. System Menampilkan komponen BOS (pilihan yang sudah ada: Belanja pegawai, Belanja barang, Belanja modal, Pembelajaran, Pemeliharaan, Lainnya bisa ditambah)
        $defaultComponents = ['Belanja pegawai', 'Belanja barang', 'Belanja modal', 'Pembelajaran', 'Pemeliharaan'];
        $existingComponents = $categories->pluck('name')->filter()->unique()->values()->all();
        $components = array_values(array_unique(array_merge($defaultComponents, $existingComponents)));

        return view('pages.keuangan.bos.anggaran', [
            'title' => 'BOS',
            'categories' => $categories,
            'metrics' => $metrics,
            'sources' => $sources,
            'components' => $components,
        ]);
    }

    /**
     * Store new BOS Master Data.
     */
    public function storeAnggaran(Request $request, SchoolContext $schoolContext)
    {
        $schoolId = $schoolContext->activeSchoolId() ?? session('active_school_id');

        $validated = $request->validate([
            'source_funding' => 'required|string|max:120',
            'name' => 'required|string|max:255',
            'custom_source' => 'nullable|string|max:120',
            'custom_component' => 'nullable|string|max:255',
        ], [
            'source_funding.required' => 'Sumber Dana wajib dipilih.',
            'name.required' => 'Komponen BOS wajib dipilih.',
        ]);

        $sourceFunding = ($validated['source_funding'] === 'Lainnya' && filled($request->input('custom_source')))
            ? trim($request->input('custom_source'))
            : $validated['source_funding'];

        $componentName = ($validated['name'] === 'Lainnya' && filled($request->input('custom_component')))
            ? trim($request->input('custom_component'))
            : $validated['name'];

        // Auto-generate code
        $count = BudgetCategory::count();
        $num = $count + 1;
        do {
            $code = 'BOS-' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);
            $num++;
        } while (BudgetCategory::where('code', $code)->exists());

        BudgetCategory::create([
            'school_id' => $schoolId,
            'code' => $code,
            'name' => $componentName,
            'source_funding' => $sourceFunding,
            'is_active' => true,
        ]);

        // Sync with IncomeType category BOS
        if ($schoolId) {
            IncomeType::firstOrCreate(
                ['school_id' => $schoolId, 'name' => $sourceFunding],
                [
                    'code' => 'BOS-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $sourceFunding), 0, 4)) . rand(10, 99),
                    'category' => 'BOS',
                ]
            );
        }

        return redirect()->route('bos.anggaran.index')
            ->with('success_modal', true)
            ->with('success', 'Data jenis pemasukan BOS berhasil disimpan ke dalam sistem.');
    }

    /**
     * Update BOS Master Data.
     */
    public function updateAnggaran(Request $request, BudgetCategory $category, SchoolContext $schoolContext)
    {
        $schoolId = $schoolContext->activeSchoolId() ?? session('active_school_id');
        if ($schoolId && $category->school_id && (int) $category->school_id !== (int) $schoolId) {
            abort(403, 'Aksi tidak diizinkan untuk sekolah ini.');
        }

        $validated = $request->validate([
            'source_funding' => 'required|string|max:120',
            'name' => 'required|string|max:255',
            'custom_source' => 'nullable|string|max:120',
            'custom_component' => 'nullable|string|max:255',
        ], [
            'source_funding.required' => 'Sumber Dana wajib dipilih.',
            'name.required' => 'Komponen BOS wajib dipilih.',
        ]);

        $sourceFunding = ($validated['source_funding'] === 'Lainnya' && filled($request->input('custom_source')))
            ? trim($request->input('custom_source'))
            : $validated['source_funding'];

        $componentName = ($validated['name'] === 'Lainnya' && filled($request->input('custom_component')))
            ? trim($request->input('custom_component'))
            : $validated['name'];

        $category->update([
            'source_funding' => $sourceFunding,
            'name' => $componentName,
        ]);

        return redirect()->route('bos.anggaran.index')
            ->with('success', 'Data sumber dana BOS berhasil diperbarui.');
    }

    /**
     * Delete BOS Master Data.
     */
    public function destroyAnggaran(BudgetCategory $category, SchoolContext $schoolContext)
    {
        $schoolId = $schoolContext->activeSchoolId() ?? session('active_school_id');
        if ($schoolId && $category->school_id && (int) $category->school_id !== (int) $schoolId) {
            abort(403, 'Aksi tidak diizinkan untuk sekolah ini.');
        }

        if (BudgetPlan::where('school_id', $schoolId)->where('budget_category_id', $category->id)->exists() || $category->expenses()->exists()) {
            return back()->with('error', 'Komponen BOS sudah digunakan dalam anggaran atau transaksi. Nonaktifkan untuk penggunaan berikutnya.');
        }
        $category->delete();

        return redirect()->route('bos.anggaran.index')
            ->with('success', 'Data sumber dana BOS berhasil dihapus.');
    }

    /**
     * Display list of expenses and record form.
     */
    public function belanja(SchoolContext $schoolContext)
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        $expenses = Expense::with(['budgetCategory', 'academicYear', 'expenseType', 'virtualWallet', 'account'])
            ->where('school_id', $schoolId)
            ->orderBy('transaction_date', 'desc')
            ->get();
            
        $categories = BudgetCategory::where('school_id', $schoolId)->where('is_active', true)->orderBy('code')->get();
        $academicYears = AcademicYear::where(function ($q) use ($schoolId) {
            $q->where('school_id', $schoolId)->orWhereNull('school_id');
        })->get();
        $activeYear = AcademicYear::where(function ($q) use ($schoolId) {
            $q->where('school_id', $schoolId)->orWhereNull('school_id');
        })->where('is_active', true)->first();

        $expenseTypes = ExpenseType::where('school_id', $schoolId)->orderBy('name')->get();
        $komiteExpenseTypes = $expenseTypes->filter(fn ($et) => strtolower($et->source_funding ?? '') === 'komite');
        if ($komiteExpenseTypes->isEmpty()) {
            // Sediakan beberapa jenis pengeluaran komite umum untuk kemudahan operasional
            $defaultKomiteNames = [
                'Konsumsi & Rapat Komite',
                'Honor & Transport Pengurus Komite',
                'Bantuan Kegiatan Kesiswaan & Lomba',
                'Pemeliharaan Fasilitas & Sarpras Komite',
                'Kegiatan Paguyuban Orang Tua Murid',
                'Operasional & Administrasi Komite',
            ];
            foreach ($defaultKomiteNames as $idx => $name) {
                ExpenseType::create([
                    'school_id' => $schoolId,
                    'code' => 'KMT-' . str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT),
                    'name' => $name,
                    'source_funding' => 'komite',
                    'requires_approval' => true,
                ]);
            }
            $expenseTypes = ExpenseType::where('school_id', $schoolId)->orderBy('name')->get();
            $komiteExpenseTypes = $expenseTypes->filter(fn ($et) => strtolower($et->source_funding ?? '') === 'komite');
        }

        $virtualWallets = VirtualWallet::where('school_id', $schoolId)->where('status', 'active')->get();
        $schoolAccounts = SchoolAccount::where('school_id', $schoolId)->where('is_active', true)->get();
        $bosBudgets = BudgetPlan::where('school_id', $schoolId)->where('status', 'approved')->whereHas('budgetYear', fn ($q) => $q->where('status', 'active'))
            ->where(fn ($q) => $q->whereRaw('LOWER(source_funding) LIKE ?', ['bos%'])->orWhereRaw('LOWER(source_funding) LIKE ?', ['bop%'])->orWhereRaw('LOWER(source_funding) LIKE ?', ['dak%']))->get();
        $bosBudgets->each(function ($plan) use ($schoolId) {
            $plan->setAttribute('remaining_amount', max(0, (float) $plan->amount - (float) Expense::where('school_id', $schoolId)->where('budget_plan_id', $plan->id)->whereNotNull('posted_at')->sum('amount')));
        });

        return view('pages.keuangan.bos.belanja', [
            'title' => 'Pencatatan Belanja & Operasional Sekolah',
            'expenses' => $expenses,
            'categories' => $categories,
            'academicYears' => $academicYears,
            'activeYear' => $activeYear,
            'expenseTypes' => $expenseTypes,
            'komiteExpenseTypes' => $komiteExpenseTypes,
            'virtualWallets' => $virtualWallets,
            'schoolAccounts' => $schoolAccounts,
            'bosBudgets' => $bosBudgets,
            'bosSources' => app(\App\Services\BosIncomeService::class)->sources((int) $schoolId),
        ]);
    }

    /**
     * Store new expense with tax calculations.
     */
    public function storeBelanja(Request $request, SchoolContext $schoolContext, FinanceService $finance)
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        $operational = $request->input('category') === 'Operasional' || ($request->input('category') !== 'Transfer Alokasi' && strtolower($request->input('source_funding', '')) === 'komite');
        if ($operational) {
            $request->merge(['source_funding' => 'Komite', 'budget_category_id' => null]);
        }
        $validated = $request->validate([
            'category' => 'nullable|string',
            'budget_category_id' => ['nullable', Rule::exists('budget_categories', 'id')->where('school_id', $schoolId)],
            'budget_plan_id' => ['nullable', Rule::exists('budget_plans', 'id')->where('school_id', $schoolId)],
            'bos_year' => ['nullable', 'integer', 'between:2020,2099'],
            'expense_type_id' => [$operational ? 'required' : 'nullable', Rule::exists('expense_types', 'id')->where('school_id', $schoolId)],
            'virtual_wallet_id' => [$operational ? 'required' : 'nullable', Rule::exists('virtual_wallets', 'id')->where('school_id', $schoolId)],
            'account_id' => ['nullable', Rule::exists('school_accounts', 'id')->where('school_id', $schoolId)],
            'academic_year_id' => ['nullable', Rule::exists('academic_years', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)->orWhereNull('school_id'))],
            'expense_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01|max:9999999999999.99|decimal:0,2',
            'transaction_date' => 'required|date',
            'source_funding' => 'required|string',
            'payment_method' => 'required|string',
            'reference_invoice' => 'nullable|string',
            'recipient_name' => [$operational ? 'required' : 'nullable', 'string', 'max:255'],
            'tax_type' => 'nullable|string',
            'tax_amount' => 'nullable|numeric|min:0',
            'is_tax_paid' => 'nullable|boolean',
            'proof' => 'nullable|file|mimes:pdf,jpg,jpeg,png,heic|max:10240',
            'document_checklist' => 'nullable|array',
        ]);

        if (empty($validated['academic_year_id'])) {
            $activeYear = AcademicYear::where('school_id', $schoolId)->where('is_active', true)->first();
            $validated['academic_year_id'] = $activeYear?->id;
        }
        if (empty($validated['academic_year_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['academic_year_id' => 'Lengkapi tahun ajaran aktif sekolah sebelum mencatat pengeluaran.']);
        }

        if (! empty($validated['budget_plan_id']) && ! $operational) {
            $plan = BudgetPlan::where('school_id', $schoolId)->findOrFail($validated['budget_plan_id']);
            $validated['source_funding'] = $plan->source_funding;
            $validated['budget_category_id'] = $plan->budget_category_id;
            $validated['bos_year'] = $validated['bos_year'] ?? (int) substr($validated['transaction_date'], 0, 4);
        }

        $validated['tax_amount'] = $request->has('tax_amount') ? $request->input('tax_amount') : 0.00;
        $validated['is_tax_paid'] = $request->has('is_tax_paid') ? (bool) $request->input('is_tax_paid') : false;

        $amount = (float) $validated['amount'];

        // Validasi nominal pengeluaran tidak boleh melebihi saldo dompet virtual atau rekening
        if (!empty($validated['virtual_wallet_id'])) {
            $wallet = VirtualWallet::where('school_id', $schoolId)->find($validated['virtual_wallet_id']);
            if ($wallet && $amount > (float) $wallet->nominal) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['amount' => 'Nominal pengeluaran melebihi saldo dompet virtual (' . $wallet->name . ': Rp ' . number_format($wallet->nominal, 0, ',', '.') . '). Transaksi tidak dapat disimpan.']);
            }
        }

        if (!empty($validated['account_id'])) {
            $account = SchoolAccount::where('school_id', $schoolId)->find($validated['account_id']);
            if ($account && $amount > (float) $account->current_balance) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['amount' => 'Nominal pengeluaran melebihi saldo rekening (' . $account->name . ': Rp ' . number_format($account->current_balance, 0, ',', '.') . '). Transaksi tidak dapat disimpan.']);
            }
        }

        $path = $request->hasFile('proof') ? $request->file('proof')->storeAs('finance/expenses/'.$schoolId, Str::uuid().'.'.$request->file('proof')->extension(), 'local') : null;
        try {
            $expense = $finance->createExpense($schoolId, $request->user()->id, [...$validated, 'proof_path' => $path]);
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }

        return redirect()->back()->with('success', 'Pengeluaran '.$expense->transaction_number.' dibuat dan menunggu approval kepala sekolah.');
    }

    /**
     * Update an expense transaction.
     */
    public function updateBelanja(Request $request, Expense $expense, SchoolContext $schoolContext)
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId && (int) $expense->school_id === (int) $schoolId, 403);
        abort_unless($expense->status === 'pending' && ! $expense->posted_at, 422, 'Hanya pengeluaran yang menunggu approval yang dapat diubah.');

        $committee = strtolower($request->input('source_funding', '')) === 'komite';
        if (! $committee) {
            $request->merge(['virtual_wallet_id' => null, 'expense_type_id' => null]);
        }
        $validated = $request->validate([
            'budget_category_id' => ['nullable', Rule::exists('budget_categories', 'id')->where('school_id', $schoolId)],
            'budget_plan_id' => ['nullable', Rule::exists('budget_plans', 'id')->where('school_id', $schoolId)],
            'bos_year' => ['nullable', 'integer', 'between:2020,2099'],
            'expense_type_id' => [$committee ? 'required' : 'nullable', Rule::exists('expense_types', 'id')->where('school_id', $schoolId)],
            'virtual_wallet_id' => [$committee ? 'required' : 'nullable', Rule::exists('virtual_wallets', 'id')->where('school_id', $schoolId)],
            'account_id' => ['nullable', Rule::exists('school_accounts', 'id')->where('school_id', $schoolId)],
            'expense_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01|max:9999999999999.99|decimal:0,2',
            'transaction_date' => 'required|date',
            'source_funding' => 'required|string',
            'payment_method' => 'required|string',
            'reference_invoice' => 'nullable|string|max:255',
            'recipient_name' => [$committee ? 'required' : 'nullable', 'string', 'max:255'],
            'tax_type' => 'nullable|string|max:50',
            'tax_amount' => 'nullable|numeric|min:0',
            'is_tax_paid' => 'nullable|boolean',
        ]);

        $amount = (float) $validated['amount'];

        // Validasi nominal pengeluaran tidak boleh melebihi saldo dompet virtual atau rekening
        if (!empty($validated['virtual_wallet_id'])) {
            $wallet = VirtualWallet::where('school_id', $schoolId)->find($validated['virtual_wallet_id']);
            if ($wallet && $amount > (float) $wallet->nominal) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['amount' => 'Nominal pengeluaran melebihi saldo dompet virtual (' . $wallet->name . ': Rp ' . number_format($wallet->nominal, 0, ',', '.') . '). Transaksi tidak dapat disimpan.']);
            }
        }

        if (!empty($validated['account_id'])) {
            $account = SchoolAccount::where('school_id', $schoolId)->find($validated['account_id']);
            if ($account && $amount > (float) $account->current_balance) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['amount' => 'Nominal pengeluaran melebihi saldo rekening (' . $account->name . ': Rp ' . number_format($account->current_balance, 0, ',', '.') . '). Transaksi tidak dapat disimpan.']);
            }
        }

        if (! empty($validated['budget_plan_id']) && ! $committee) {
            $plan = BudgetPlan::where('school_id', $schoolId)->findOrFail($validated['budget_plan_id']);
            $validated['source_funding'] = $plan->source_funding;
            $validated['budget_category_id'] = $plan->budget_category_id;
        }

        $validated['tax_amount'] = $request->filled('tax_amount') ? (float) $request->input('tax_amount') : 0.00;
        $validated['is_tax_paid'] = $request->has('is_tax_paid') ? (bool) $request->input('is_tax_paid') : false;

        DB::transaction(function () use ($expense, $schoolId, $validated) {
            ApprovalRequest::where('school_id', $schoolId)->where('approvable_type', Expense::class)->where('approvable_id', $expense->id)->lockForUpdate()->get();
            $expense = Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            abort_unless($expense->status === 'pending' && ! $expense->posted_at, 422, 'Pengeluaran ini sudah diproses.');
            [$account, $wallet] = app(ExpensePostingService::class)->sources($schoolId, [...$expense->getAttributes(), ...$validated]);
            $expense->update([...$validated, 'account_id' => $account?->id, 'virtual_wallet_id' => $wallet?->id]);
        });

        return redirect()->back()->with('success', 'Transaksi pengeluaran berhasil diperbarui.');
    }

    public function expenseProof(Expense $expense, SchoolContext $schoolContext)
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless((int) $expense->school_id === $schoolId && $expense->proof_path, 404);
        $path = $expense->proof_path;
        abort_if(str_contains($path, '..'), 404);
        $disk = str_starts_with($path, 'finance/expenses/'.$schoolId.'/') ? Storage::disk('local') : Storage::disk('public');
        abort_unless(str_starts_with($path, 'finance/expenses/') && $disk->exists($path), 404);

        return $disk->download($path, 'Bukti-Pengeluaran-'.$expense->id.'.'.pathinfo($path, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
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
