<?php

namespace App\Services;

use App\Models\{ApprovalRequest, BookClosing, BudgetYear, Expense, FinanceIncome, FinanceWalletMovement, PaymentSubmission, SchoolAccount, User, VirtualWallet};
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceClosingService
{
    public function __construct(private readonly FinanceLedgerService $ledger, private readonly FinancePeriodGuard $periods) {}

    public function validate(int $schoolId, ?string $type = null, ?string $period = null): array
    {
        [$start, $end, $source] = $type && $period ? $this->periods->bounds($schoolId, $type, $period) : [null, null, null];
        $within = fn ($date) => $date && (! $start || Carbon::parse($date)->toDateString() >= $start) && (! $end || Carbon::parse($date)->toDateString() <= $end);
        $matchesSource = fn ($funding, $bosYear = null) => $type !== 'bos' || ((! $source || strcasecmp($source, $funding ?? '') === 0) && ($bosYear || preg_match('/^(bos|bop|dak)/i', $funding ?? '')));
        $expenses = Expense::where('school_id', $schoolId)->get()->filter(fn ($expense) => $within($expense->transaction_date) && $matchesSource($expense->source_funding, $expense->bos_year));
        $payments = PaymentSubmission::where('school_id', $schoolId)->get()->filter(fn ($payment) => $within($payment->payment_date) && $type !== 'bos');
        $incomes = FinanceIncome::where('school_id', $schoolId)->get()->filter(fn ($income) => $within($income->received_date) && $matchesSource($income->source_funding, $income->bos_year));
        $pendingApprovals = ApprovalRequest::where('school_id', $schoolId)->where('status', 'pending')
            ->whereIn('type', ['expense', 'payment', 'budget', 'budget_revision', 'bos_income_revision', 'grant_income_revision', 'other_income_revision'])
            ->with('approvable')->get()->filter(function ($approval) use ($within, $matchesSource, $start, $end, $type) {
                $record = $approval->approvable;
                if (! $record) {
                    return true;
                }
                if ($approval->type === 'expense') {
                    return $within($record->transaction_date) && $matchesSource($record->source_funding, $record->bos_year);
                }
                if ($approval->type === 'payment') {
                    return $type !== 'bos' && $within($record->payment_date);
                }
                if (str_ends_with($approval->type, '_income_revision')) {
                    $old = $record->old_data ?? [];
                    $new = $record->new_data ?? [];
                    $income = FinanceIncome::find($record->finance_income_id);
                    return ($within($old['received_date'] ?? $income?->received_date) || $within($new['received_date'] ?? $income?->received_date))
                        && $matchesSource($old['source_funding'] ?? $income?->source_funding, $old['bos_year'] ?? $income?->bos_year);
                }
                $plan = $approval->type === 'budget_revision' ? $record->budgetPlan : $record;
                $year = $plan?->budgetYear;
                return (! $start || ! $year || ($year->start_date->toDateString() <= $end && $year->end_date->toDateString() >= $start))
                    && $matchesSource($plan?->source_funding);
            })->count();

        $accounts = SchoolAccount::where('school_id', $schoolId)->get();
        $entries = $this->ledger->entries($schoolId);
        $mismatches = $accounts->filter(function ($account) use ($entries) {
            $movement = $entries->where('account_id', $account->id)->sum(fn ($entry) => BillingPaymentService::cents($entry['debet']) - BillingPaymentService::cents($entry['kredit']));
            return BillingPaymentService::cents($account->opening_balance) + $movement !== BillingPaymentService::cents($account->current_balance);
        });
        $negativeAccounts = $accounts->where('current_balance', '<', 0)->count();
        $negativeWallets = VirtualWallet::where('school_id', $schoolId)->where('nominal', '<', 0)->count();
        $unpaidTaxes = $expenses->where('status', 'approved')->where('tax_amount', '>', 0)->where('is_tax_paid', false)->count();
        $missingDocuments = $expenses->where('status', 'approved')->filter(fn ($expense) => ! $expense->proof_path)->count()
            + $incomes->where('status', 'approved')->filter(fn ($income) => ($income->bos_year || preg_match('/^(bos|bop|dak)/i', $income->source_funding)) && ! $income->proof_path)->count();
        $unposted = $expenses->where('status', 'approved')->whereNull('posted_at')->count() + $payments->where('status', 'approved')->whereNull('posted_at')->count();
        $bosMismatches = $mismatches->filter(fn ($account) => strtolower($account->source_funding ?? '') === 'bos' || strtolower($account->type) === 'bos')->count();
        $checks = [
            'pending_approvals' => $pendingApprovals,
            'pending_payments' => $payments->where('status', 'pending')->count(),
            'pending_expenses' => $expenses->where('status', 'pending')->count() + $incomes->where('status', 'pending')->count(),
            'negative_accounts' => $negativeAccounts + $negativeWallets,
            'failed_journals' => $unposted,
            'incomplete_transfers' => $payments->where('status', 'pending')->count(),
            'unpaid_taxes' => $unpaidTaxes,
            'account_reconciliation' => $mismatches->count(),
            'bos_balance' => $bosMismatches,
            'missing_documents' => $missingDocuments,
            'journal_mode' => 'synchronous',
        ];
        $messages = [
            'pending_approvals' => 'pengajuan keuangan belum disetujui', 'pending_payments' => 'transfer pembayaran belum diverifikasi',
            'pending_expenses' => 'transaksi masih pending', 'negative_accounts' => 'rekening/dompet bersaldo negatif',
            'failed_journals' => 'transaksi approved belum diposting; periksa posting sebelum tutup buku',
            'unpaid_taxes' => 'pajak belum disetor', 'account_reconciliation' => 'rekening tidak sesuai saldo awal dan transaksi tercatat',
            'missing_documents' => 'bukti pengeluaran/SP2D BOS belum diunggah',
        ];
        $checks['error_messages'] = [];
        foreach ($messages as $key => $message) {
            if ($checks[$key] > 0) {
                $checks['error_messages'][] = $checks[$key].' '.$message.'.';
            }
        }
        $checks['all_passed'] = $checks['error_messages'] === [];
        return $checks;
    }

    public function close(int $schoolId, string $type, string $period, int $actorId): BookClosing
    {
        [$start, $end, $source, $yearId] = $this->periods->bounds($schoolId, $type, $period);
        return DB::transaction(function () use ($schoolId, $type, $period, $actorId, $start, $end, $source, $yearId) {
            DB::table('schools')->where('id', $schoolId)->lockForUpdate()->first();
            if (BookClosing::where('school_id', $schoolId)->where('status', 'closed')->where('type', $type)->where('period', $period)->exists()) {
                throw ValidationException::withMessages(['closing' => 'Periode ini sudah ditutup.']);
            }
            $validation = $this->validate($schoolId, $type, $period);
            if (! $validation['all_passed']) {
                throw ValidationException::withMessages(['closing' => 'Tutup buku tidak dapat dilakukan: '.implode(' ', $validation['error_messages'])]);
            }
            $entries = $this->ledger->entries($schoolId);
            $after = $entries->filter(fn ($entry) => $entry['date']->toDateString() > $end);
            $accounts = SchoolAccount::where('school_id', $schoolId)->orderBy('id')->get()->map(function ($account) use ($after) {
                $future = $after->where('account_id', $account->id)->sum(fn ($entry) => BillingPaymentService::cents($entry['debet']) - BillingPaymentService::cents($entry['kredit']));
                return ['id' => $account->id, 'name' => $account->name, 'type' => $account->type, 'balance' => (BillingPaymentService::cents($account->current_balance) - $future) / 100];
            })->all();
            $wallets = VirtualWallet::where('school_id', $schoolId)->orderBy('id')->get()->map(function ($wallet) use ($schoolId, $end) {
                $future = FinanceWalletMovement::where('school_id', $schoolId)->where('virtual_wallet_id', $wallet->id)->whereDate('effective_date', '>', $end)->sum('amount');
                return ['id' => $wallet->id, 'name' => $wallet->name, 'balance' => (BillingPaymentService::cents($wallet->nominal) - BillingPaymentService::cents($future)) / 100];
            })->all();
            $periodEntries = $entries->filter(fn ($entry) => $entry['date']->toDateString() >= $start && $entry['date']->toDateString() <= $end && (! $source || strcasecmp($entry['source_funding'], $source) === 0));
            $closing = BookClosing::create([
                'school_id' => $schoolId, 'closed_by' => $actorId, 'type' => $type, 'period' => $period, 'period_start' => $start, 'period_end' => $end,
                'source_funding' => $source, 'budget_year_id' => $yearId, 'status' => 'closed',
                'snapshot' => ['period' => $period, 'type' => $type, 'accounts' => $accounts, 'wallets' => $wallets,
                    'summary' => ['collected' => $periodEntries->where('type', '!=', 'transfer')->sum('debet'), 'spent' => $periodEntries->where('type', '!=', 'transfer')->sum('kredit')],
                    'entries' => $periodEntries->values()->map(fn ($entry) => [...$entry, 'date' => $entry['date']->toDateString()])->all(),
                    'next_opening' => ['date' => Carbon::parse($end)->addDay()->toDateString(), 'accounts' => $accounts, 'wallets' => $wallets]],
                'validation_results' => $validation, 'audit_metadata' => ['actor_id' => $actorId, 'closed_at' => now()->toISOString(), 'source' => 'finance_closing'], 'closed_at' => now(),
            ]);
            if ($yearId) {
                BudgetYear::whereKey($yearId)->update(['status' => 'closed']);
            }
            return $closing;
        });
    }

    public function reopen(BookClosing $closing, User $actor, string $reason): void
    {
        $authorised = DB::table('school_user_roles')->where('school_id', $closing->school_id)->where('user_id', $actor->id)
            ->where('is_active', true)->where('membership_status', 'active')->whereIn('role', ['kepsek', 'super_admin'])->exists();
        if (! $authorised) {
            throw new AuthorizationException('Hanya kepala sekolah atau super admin aktif sekolah yang dapat membuka kembali buku.');
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['reason' => 'Isi alasan pembukaan kembali minimal 5 karakter.']);
        }
        DB::transaction(function () use ($closing, $actor, $reason) {
            DB::table('schools')->where('id', $closing->school_id)->lockForUpdate()->first();
            $closing = BookClosing::whereKey($closing->id)->lockForUpdate()->firstOrFail();
            if ($closing->status !== 'closed') {
                throw ValidationException::withMessages(['closing' => 'Periode sudah terbuka.']);
            }
            $end = $closing->period_end ?: $this->periods->bounds($closing->school_id, $closing->type, $closing->period)[1];
            $later = BookClosing::where('school_id', $closing->school_id)->where('status', 'closed')->where('id', '!=', $closing->id)->get()
                ->contains(fn ($other) => ($other->period_end ?: $this->periods->bounds($other->school_id, $other->type, $other->period)[1]) > $end);
            if ($later) {
                throw ValidationException::withMessages(['closing' => 'Buka kembali periode berikutnya terlebih dahulu agar saldo awal tetap konsisten.']);
            }
            $closing->update(['status' => 'reopened', 'audit_metadata' => [...($closing->audit_metadata ?? []), 'reopened_by' => $actor->id, 'reopened_at' => now()->toISOString(), 'reopen_reason' => trim($reason)]]);
            if ($closing->budget_year_id) {
                BudgetYear::whereKey($closing->budget_year_id)->update(['status' => 'active']);
            }
        });
    }
}
