<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\BookClosing;
use App\Models\BudgetPlan;
use App\Models\Expense;
use App\Models\FinanceAccountTransfer;
use App\Models\FinanceIncome;
use App\Models\FinanceWalletMovement;
use App\Models\SchoolAccount;
use App\Models\VirtualWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BosClosingService
{
    public const CHECKS = [
        'pending_approvals' => 'Persetujuan transaksi', 'negative_balances' => 'Saldo negatif',
        'unposted' => 'Posting transaksi', 'invalid_transfers' => 'Transfer antar rekening',
        'balance_mismatches' => 'Keseimbangan BOS', 'unpaid_taxes' => 'Setoran pajak',
        'pending_transactions' => 'Transaksi pending', 'invalid_accounts' => 'Kesesuaian rekening',
    ];

    public function __construct(private BosIncomeService $bos, private FinanceLedgerService $ledger) {}

    public function sources(int $schoolId): \Illuminate\Support\Collection
    {
        $known = $this->bos->sources($schoolId);

        return $known
            ->merge(FinanceIncome::where('school_id', $schoolId)->distinct()->pluck('source_funding'))
            ->merge(Expense::where('school_id', $schoolId)->distinct()->pluck('source_funding'))
            ->merge(BookClosing::where('school_id', $schoolId)->where('type', 'bos')->distinct()->pluck('source_funding'))
            ->filter(fn ($source) => $source && ($known->contains($source) || preg_match('/^(bos|bop|dak)/i', $source)))
            ->unique()->sort()->values();
    }

    private function matches(mixed $funding, mixed $fiscalYear, mixed $date, string $source, int $year): bool
    {
        return strcasecmp((string) $funding, $source) === 0
            && ($fiscalYear ? (int) $fiscalYear === $year : substr((string) $date, 0, 4) === (string) $year);
    }

    public function preview(int $schoolId, string $source, int $year): array
    {
        if (! $this->sources($schoolId)->contains($source) || ! $this->bos->years($schoolId)->contains($year)) {
            throw ValidationException::withMessages(['bos_year' => 'Pilih sumber dan tahun BOS yang tersedia untuk sekolah aktif.']);
        }
        $start = $year.'-01-01';
        $end = $year.'-12-31';
        $matches = fn ($record) => $this->matches($record->source_funding, $record->bos_year, $record->received_date ?? $record->transaction_date, $source, $year);
        $incomes = FinanceIncome::where('school_id', $schoolId)->orderBy('id')->get()->filter($matches);
        $expenses = Expense::with('budgetCategory')->where('school_id', $schoolId)->orderBy('id')->get()->filter($matches);
        $records = $incomes->concat($expenses)->reject(fn ($record) => in_array($record->status, ['rejected', 'cancelled', 'canceled'], true));
        $posted = $records->filter(fn ($record) => in_array($record->status, ['approved', 'confirmed'], true) && $record->posted_at);
        $entries = $posted->map(fn ($record) => [
            'id' => $record->id, 'type' => $record instanceof FinanceIncome ? 'income' : 'expense',
            'date' => ($record->received_date ?? $record->transaction_date)->toDateString(),
            'ref' => ($record instanceof FinanceIncome ? 'INC-' : 'EXP-').$record->id,
            'description' => $record instanceof FinanceIncome ? 'Penerimaan '.$record->source_funding : $record->expense_name,
            'amount' => $record->amount, 'debet' => $record instanceof FinanceIncome ? $record->amount : 0,
            'kredit' => $record instanceof Expense ? $record->amount : 0,
            'account_id' => $record->account_id, 'bos_year' => $record->bos_year,
            'source_funding' => $record->source_funding, 'proof_path' => $record->proof_path,
            'tax_amount' => $record->tax_amount ?? 0, 'is_tax_paid' => (bool) $record->is_tax_paid,
            'version' => $record->updated_at?->toISOString(),
        ])->sortBy(fn ($entry) => $entry['date'].'-'.$entry['type'].'-'.str_pad((string) $entry['id'], 12, '0', STR_PAD_LEFT))->values();
        $all = $this->ledger->entries($schoolId);
        $accountModels = SchoolAccount::where('school_id', $schoolId)->orderBy('id')->get()
            ->filter(fn ($account) => strcasecmp($account->source_funding ?? '', 'bos') === 0 || strcasecmp($account->type, 'bos') === 0 || $records->pluck('account_id')->contains($account->id));
        $accounts = $accountModels->map(function ($account) use ($all, $end) {
            $future = $all->filter(fn ($entry) => $entry['account_id'] == $account->id && $entry['date']->toDateString() > $end)
                ->sum(fn ($entry) => BillingPaymentService::cents($entry['debet']) - BillingPaymentService::cents($entry['kredit']));

            return ['id' => $account->id, 'name' => $account->name, 'number' => $account->account_number, 'type' => $account->type,
                'balance' => (BillingPaymentService::cents($account->current_balance) - $future) / 100];
        })->values()->all();
        $wallets = VirtualWallet::where('school_id', $schoolId)->orderBy('id')->get()->filter(fn ($wallet) => preg_match('/^(bos|bop|dak)/i', $wallet->source ?? ''))
            ->map(function ($wallet) use ($schoolId, $end) {
                $future = FinanceWalletMovement::where('school_id', $schoolId)->where('virtual_wallet_id', $wallet->id)->whereDate('effective_date', '>', $end)->sum('amount');

                return ['id' => $wallet->id, 'name' => $wallet->name, 'balance' => (BillingPaymentService::cents($wallet->nominal) - BillingPaymentService::cents($future)) / 100];
            })->values()->all();
        $pendingApprovals = ApprovalRequest::with('approvable')->where('school_id', $schoolId)->where('status', 'pending')
            ->whereIn('type', ['expense', 'bos_income_revision'])->get()->filter(function ($approval) use ($matches, $source, $year) {
                $record = $approval->approvable;
                if (! $record) {
                    return true;
                }
                if ($approval->type === 'expense') {
                    return $matches($record);
                }
                $income = FinanceIncome::where('school_id', $approval->school_id)->find($record->finance_income_id);

                return collect([$record->old_data ?? [], $record->new_data ?? []])->contains(fn ($data) => $this->matches(
                    $data['source_funding'] ?? $income?->source_funding, $data['bos_year'] ?? $income?->bos_year,
                    $data['received_date'] ?? $income?->received_date, $source, $year));
            })->count();
        $transfers = FinanceAccountTransfer::where('school_id', $schoolId)->whereBetween('transfer_date', [$start, $end])->get()
            ->filter(fn ($transfer) => $accountModels->contains('id', $transfer->from_account_id) || $accountModels->contains('id', $transfer->to_account_id));
        $validAccountIds = SchoolAccount::where('school_id', $schoolId)->pluck('id');
        $checks = [
            'pending_approvals' => $pendingApprovals,
            'negative_balances' => collect($accounts)->concat($wallets)->where('balance', '<', 0)->count(),
            'unposted' => $records->filter(fn ($record) => in_array($record->status, ['approved', 'confirmed'], true) && ! $record->posted_at)->count(),
            'invalid_transfers' => $transfers->filter(fn ($transfer) => $transfer->amount <= 0 || $transfer->from_account_id === $transfer->to_account_id || ! $validAccountIds->contains($transfer->from_account_id) || ! $validAccountIds->contains($transfer->to_account_id))->count(),
            'balance_mismatches' => $accountModels->filter(function ($account) use ($all) {
                $movement = $all->where('account_id', $account->id)->sum(fn ($entry) => BillingPaymentService::cents($entry['debet']) - BillingPaymentService::cents($entry['kredit']));

                return BillingPaymentService::cents($account->opening_balance) + $movement !== BillingPaymentService::cents($account->current_balance);
            })->count(),
            'unpaid_taxes' => $expenses->whereIn('status', ['approved', 'confirmed'])->where('tax_amount', '>', 0)->where('is_tax_paid', false)->count(),
            'pending_transactions' => $records->reject(fn ($record) => in_array($record->status, ['approved', 'confirmed'], true))->count(),
            'invalid_accounts' => $records->whereIn('status', ['approved', 'confirmed'])->filter(fn ($record) => ! $accountModels->contains(fn ($account) => $account->id === $record->account_id && (strcasecmp($account->source_funding ?? '', 'bos') === 0 || strcasecmp($account->type, 'bos') === 0)))->count(),
            'ambiguous_years' => $records->whereNull('bos_year')->count(),
            'missing_documents' => $records->whereIn('status', ['approved', 'confirmed'])->filter(fn ($record) => ! $record->proof_path || ! Storage::disk('public')->exists($record->proof_path))->count(),
        ];
        $errors = [];
        foreach (self::CHECKS + ['ambiguous_years' => 'Tahun BOS belum ditentukan', 'missing_documents' => 'Bukti transaksi tidak tersedia'] as $key => $label) {
            if ($checks[$key] > 0) {
                $errors[] = $label.': '.$checks[$key].' item perlu diperiksa.';
            }
        }
        $overlap = BookClosing::where('school_id', $schoolId)->where('type', 'bos')->where('status', 'closed')->get()->contains(function ($closing) use ($schoolId, $source, $start, $end) {
            [$a, $b, $funding] = $closing->period_start ? [$closing->period_start, $closing->period_end, $closing->source_funding] : app(FinancePeriodGuard::class)->bounds($schoolId, 'bos', $closing->period);

            return (! $funding || strcasecmp($funding, $source) === 0) && $a <= $start && $b >= $end;
        });
        if ($overlap) {
            $errors[] = 'Tahun/sumber ini sudah ditutup atau memiliki penutupan periode BOS. Tinjau riwayat terlebih dahulu.';
        }
        $plans = BudgetPlan::where('school_id', $schoolId)->where('status', 'approved')->whereRaw('LOWER(source_funding) = ?', [strtolower($source)])
            ->whereHas('budgetYear', fn ($q) => $q->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start))->orderBy('id')->get(['id', 'activity_name', 'amount'])->toArray();
        $categories = $expenses->whereIn('status', ['approved', 'confirmed'])->whereNotNull('posted_at')->groupBy(fn ($expense) => $expense->budgetCategory?->name ?? 'Tanpa komponen anggaran')
            ->map(fn ($group, $name) => ['name' => $name, 'amount' => $group->sum(fn ($expense) => BillingPaymentService::cents($expense->amount)) / 100])->values()->all();
        $data = ['book' => 'bos', 'year' => $year, 'source' => $source, 'start' => $start, 'end' => $end,
            'entries' => $entries->all(), 'accounts' => $accounts, 'wallets' => $wallets, 'budgets' => $plans, 'categories' => $categories,
            'summary' => ['collected' => $entries->sum(fn ($entry) => BillingPaymentService::cents($entry['debet'])) / 100,
                'spent' => $entries->sum(fn ($entry) => BillingPaymentService::cents($entry['kredit'])) / 100,
                'account_balance' => collect($accounts)->sum('balance'), 'budget' => $plans ? collect($plans)->sum('amount') : 0],
            'validation' => [...$checks, 'all_passed' => $errors === [], 'error_messages' => $errors, 'transaction_count' => $posted->count(), 'transfer_count' => $transfers->count()]];
        $data['fingerprint'] = hash('sha256', json_encode($data));

        return $data;
    }

    public function close(int $schoolId, string $source, int $year, int $actorId, string $fingerprint, ?string $notes = null): BookClosing
    {
        return DB::transaction(function () use ($schoolId, $source, $year, $actorId, $fingerprint, $notes) {
            DB::table('schools')->where('id', $schoolId)->lockForUpdate()->first();
            $data = $this->preview($schoolId, $source, $year);
            if (! hash_equals($data['fingerprint'], $fingerprint)) {
                throw ValidationException::withMessages(['closing' => 'Data berubah. Jalankan Cek Kesiapan kembali sebelum konfirmasi.']);
            }
            if (! $data['validation']['all_passed']) {
                throw ValidationException::withMessages(['closing' => implode(' ', $data['validation']['error_messages'])]);
            }
            $closing = BookClosing::create(['school_id' => $schoolId, 'type' => 'bos', 'period' => $source.' '.$year,
                'period_start' => $data['start'], 'period_end' => $data['end'], 'source_funding' => $source,
                'status' => 'closed', 'closed_by' => $actorId, 'closed_at' => now(), 'validation_results' => $data['validation'],
                'snapshot' => [...$data, 'next_opening' => ['date' => ($year + 1).'-01-01', 'accounts' => $data['accounts'], 'wallets' => $data['wallets']]],
                'audit_metadata' => ['actor_id' => $actorId, 'closed_at' => now()->toISOString(), 'notes' => trim($notes ?? ''), 'source' => 'bos_closing', 'review_fingerprint' => $fingerprint]]);
            $closing->update(['audit_metadata' => [...$closing->audit_metadata, 'reference' => 'TB-BOS-'.$year.'-'.str_pad((string) $closing->id, 5, '0', STR_PAD_LEFT)]]);

            return $closing;
        });
    }
}
