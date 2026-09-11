<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\BillingItem;
use App\Models\BookClosing;
use App\Models\BudgetPlan;
use App\Models\BudgetYear;
use App\Models\ExpenseType;
use App\Models\IncomeType;
use App\Models\PaymentSubmission;
use App\Models\SchoolAccount;
use App\Services\FinanceLedgerService;
use App\Services\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceFoundationController extends Controller
{
    public function accounts(SchoolContext $schoolContext, FinanceLedgerService $ledger): View
    {
        $schoolId = $schoolContext->activeSchoolId();

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

    public function storeAccount(Request $request, SchoolContext $schoolContext): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:80'],
        ]);

        SchoolAccount::create([
            ...$validated,
            'school_id' => $schoolContext->activeSchoolId(),
            'type' => $validated['type'] ?: 'Tunai',
        ]);

        return back()->with('success', 'Rekening sekolah berhasil ditambahkan.');
    }

    public function incomeTypes(SchoolContext $schoolContext): View
    {
        return $this->typePage('Jenis Pemasukan', 'income', IncomeType::where('school_id', $schoolContext->activeSchoolId())->latest()->get(['code', 'name', 'category', 'requires_approval']));
    }

    public function expenseTypes(SchoolContext $schoolContext): View
    {
        return $this->typePage('Jenis Pengeluaran', 'expense', ExpenseType::where('school_id', $schoolContext->activeSchoolId())->latest()->get(['code', 'name', 'source_funding', 'requires_approval']));
    }

    public function budgets(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolId();

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
        return view('pages.foundation.index', [
            'title' => 'Tagihan Komite',
            'eyebrow' => 'Billing Generalized',
            'description' => 'SPP digeneralisasi menjadi tagihan sekolah, tingkat, kelas, jurusan, atau siswa.',
            'metrics' => [
                ['label' => 'Tagihan', 'value' => BillingItem::where('school_id', $schoolContext->activeSchoolId())->count()],
                ['label' => 'Transfer Pending', 'value' => PaymentSubmission::where('school_id', $schoolContext->activeSchoolId())->where('status', 'pending')->count()],
            ],
            'rows' => BillingItem::where('school_id', $schoolContext->activeSchoolId())->latest()->get(['name', 'amount', 'target_type', 'due_date', 'status']),
            'columns' => ['name' => 'Tagihan', 'amount' => 'Nominal', 'target_type' => 'Target', 'due_date' => 'Deadline', 'status' => 'Status'],
        ]);
    }

    public function closing(SchoolContext $schoolContext): View
    {
        return view('pages.foundation.index', [
            'title' => 'Tutup Buku',
            'eyebrow' => 'Financial Closing',
            'description' => 'Tutup buku bulanan, tahunan, dan BOS menyimpan snapshot saldo dan mengunci periode.',
            'metrics' => [
                ['label' => 'Periode Ditutup', 'value' => BookClosing::where('school_id', $schoolContext->activeSchoolId())->count()],
            ],
            'rows' => BookClosing::where('school_id', $schoolContext->activeSchoolId())->latest()->get(['type', 'period', 'status', 'closed_at']),
            'columns' => ['type' => 'Jenis', 'period' => 'Periode', 'status' => 'Status', 'closed_at' => 'Ditutup Pada'],
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
}
