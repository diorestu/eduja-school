<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\BillingItem;
use App\Models\BookClosing;
use App\Models\BudgetPlan;
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

        return view('pages.foundation.index', [
            'title' => 'Rekening & Wallet Sekolah',
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
            'type' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:80'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
        ]);

        $finance->createAccount($schoolId, $validated);

        return back()->with('success', 'Rekening sekolah berhasil ditambahkan.');
    }

    public function incomeTypes(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();

        return $this->typePage('Jenis Pemasukan', 'income', IncomeType::where('school_id', $schoolId)->latest()->get(['code', 'name', 'category', 'requires_approval']));
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
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40'],
            'category' => ['nullable', 'string', 'max:50'],
            'uses_allocation' => ['nullable', 'boolean'],
            'requires_approval' => ['nullable', 'boolean'],
        ]);

        $finance->createIncomeType($schoolId, $validated);

        return back()->with('success', 'Jenis pemasukan berhasil ditambahkan.');
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

        return view('pages.foundation.index', [
            'title' => 'Tahun Anggaran',
            'eyebrow' => 'Budget Control',
            'description' => 'Periode anggaran yang tersedia untuk rencana kegiatan sekolah.',
            'rows' => BudgetYear::where('school_id', $schoolId)->latest()->get(['name', 'start_date', 'end_date', 'status']),
            'columns' => ['name' => 'Nama', 'start_date' => 'Mulai', 'end_date' => 'Selesai', 'status' => 'Status'],
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

    public function budgets(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();

        return view('pages.foundation.index', [
            'title' => 'Anggaran & Revisi',
            'eyebrow' => 'Budget Control',
            'description' => 'Tahun anggaran, program, aktivitas, revisi, dan status approval.',
            'metrics' => [
                ['label' => 'Tahun Anggaran', 'value' => BudgetYear::where('school_id', $schoolId)->count()],
                ['label' => 'Rencana', 'value' => BudgetPlan::where('school_id', $schoolId)->count()],
                ['label' => 'Pending', 'value' => BudgetPlan::where('school_id', $schoolId)->where('status', 'pending')->count()],
            ],
            'rows' => BudgetPlan::where('school_id', $schoolId)->latest()->get(['source_funding', 'program_name', 'activity_name', 'amount', 'status']),
            'columns' => ['source_funding' => 'Sumber', 'program_name' => 'Program', 'activity_name' => 'Kegiatan', 'amount' => 'Anggaran', 'status' => 'Status'],
        ]);
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
        return view('pages.foundation.index', [
            'title' => 'Approval Transfer & Anggaran',
            'eyebrow' => 'Approval Queue',
            'description' => 'Transfer, revisi anggaran, dan pengeluaran masuk antrian sebelum valid.',
            'metrics' => [
                ['label' => 'Approval Pending', 'value' => ApprovalRequest::where('school_id', $schoolContext->activeSchoolId())->where('status', 'pending')->count()],
                ['label' => 'Bukti Transfer', 'value' => PaymentSubmission::where('school_id', $schoolContext->activeSchoolId())->count()],
            ],
            'rows' => ApprovalRequest::where('school_id', $schoolContext->activeSchoolId())->where('type', 'expense')->latest()->get(['id', 'type', 'status', 'note']),
            'columns' => ['type' => 'Tipe', 'status' => 'Status', 'note' => 'Catatan'],
            'approvalActions' => true,
        ]);
    }

    public function billing(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();

        return view('pages.foundation.index', [
            'title' => 'Tagihan Komite',
            'eyebrow' => 'Billing Generalized',
            'description' => 'SPP digeneralisasi menjadi tagihan sekolah, tingkat, kelas, jurusan, atau siswa.',
            'metrics' => [
                ['label' => 'Tagihan', 'value' => BillingItem::where('school_id', $schoolId)->count()],
                ['label' => 'Transfer Pending', 'value' => PaymentSubmission::where('school_id', $schoolId)->where('status', 'pending')->count()],
            ],
            'rows' => BillingItem::where('school_id', $schoolId)->latest()->get(['name', 'amount', 'target_type', 'due_date', 'status']),
            'columns' => ['name' => 'Tagihan', 'amount' => 'Nominal', 'target_type' => 'Target', 'due_date' => 'Deadline', 'status' => 'Status'],
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
            'status' => 'draft',
        ]);

        return back()->with('success', 'Tagihan berhasil disimpan sebagai draft.');
    }

    public function closing(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();

        return view('pages.foundation.index', [
            'title' => 'Tutup Buku',
            'eyebrow' => 'Financial Closing',
            'description' => 'Tutup buku bulanan, tahunan, dan BOS menyimpan snapshot saldo dan mengunci periode.',
            'metrics' => [
                ['label' => 'Periode Ditutup', 'value' => BookClosing::where('school_id', $schoolId)->count()],
            ],
            'rows' => BookClosing::where('school_id', $schoolId)->latest()->get(['type', 'period', 'status', 'closed_at']),
            'columns' => ['type' => 'Jenis', 'period' => 'Periode', 'status' => 'Status', 'closed_at' => 'Ditutup Pada'],
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

        return back()->with('success', 'Periode finance berhasil ditutup.');
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

    public function reports(SchoolContext $schoolContext, FinanceLedgerService $ledger): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        $summary = $ledger->summary($schoolId);

        return view('pages.foundation.index', [
            'title' => 'Laporan Finance',
            'eyebrow' => 'Finance Reports',
            'description' => 'Ringkasan finance sekolah aktif berdasarkan transaksi yang sudah disetujui.',
            'summary' => $summary,
            'metrics' => [
                ['label' => 'Terkumpul', 'value' => 'Rp '.number_format($summary['collected'], 0, ',', '.')],
                ['label' => 'Terpakai', 'value' => 'Rp '.number_format($summary['spent'], 0, ',', '.')],
                ['label' => 'Tunggakan', 'value' => 'Rp '.number_format($summary['outstanding'], 0, ',', '.')],
                ['label' => 'Saldo Rekening', 'value' => 'Rp '.number_format($summary['account_balance'], 0, ',', '.')],
            ],
            'sections' => [[
                'title' => 'Ringkasan periode berjalan',
                'items' => [
                    'Penerimaan dan pengeluaran hanya dihitung dari data approved.',
                    'Saldo rekening mengikuti rekening sekolah aktif.',
                ],
            ]],
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
