<?php

namespace App\Services;

use App\Models\BookClosing;
use App\Models\BudgetPlan;
use App\Models\BudgetYear;
use App\Models\FinanceWalletMovement;
use App\Models\SchoolAccount;
use App\Models\VirtualWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommitteeClosingService
{
    public const REPORTS = [
        'budget' => 'Laporan Realisasi Anggaran', 'cash' => 'Laporan Posisi Kas',
        'income' => 'Laporan Pemasukan', 'expense' => 'Laporan Pengeluaran',
        'ledger' => 'Buku Kas Umum', 'category' => 'Laporan Per Pos Anggaran',
        'minutes' => 'Berita Acara Penutupan', 'audit' => 'Audit Trail',
    ];

    public function __construct(private FinanceClosingService $closing, private FinanceLedgerService $ledger, private FinancePeriodGuard $periods) {}

    public function preview(int $schoolId, string $type, string $period): array
    {
        if (! in_array($type, ['monthly', 'yearly'], true)) {
            throw ValidationException::withMessages(['type' => 'Pilih periode bulanan atau tahunan Komite.']);
        }
        if ($type === 'yearly' && ! BudgetYear::where('school_id', $schoolId)->whereKey($period)->exists()) {
            throw ValidationException::withMessages(['period' => 'Pilih tahun anggaran milik sekolah aktif.']);
        }
        [$start, $end, , $yearId] = $this->periods->bounds($schoolId, $type, $period);
        $all = $this->ledger->entries($schoolId);
        $entries = $all->filter(fn ($entry) => $entry['date']->toDateString() >= $start && $entry['date']->toDateString() <= $end)
            ->reject(fn ($entry) => preg_match('/^(bos|bop|dak)/i', $entry['source_funding'] ?? '') || ! empty($entry['bos_year']))->values();
        $accounts = SchoolAccount::where('school_id', $schoolId)->orderBy('id')->get()->map(function ($account) use ($all, $end) {
            $future = $all->filter(fn ($entry) => $entry['date']->toDateString() > $end && $entry['account_id'] == $account->id)
                ->sum(fn ($entry) => BillingPaymentService::cents($entry['debet']) - BillingPaymentService::cents($entry['kredit']));

            return ['id' => $account->id, 'name' => $account->name, 'type' => $account->type, 'balance' => (BillingPaymentService::cents($account->current_balance) - $future) / 100];
        })->all();
        $wallets = VirtualWallet::where('school_id', $schoolId)->orderBy('id')->get()->map(function ($wallet) use ($schoolId, $end) {
            $future = FinanceWalletMovement::where('school_id', $schoolId)->where('virtual_wallet_id', $wallet->id)->whereDate('effective_date', '>', $end)->sum('amount');

            return ['id' => $wallet->id, 'name' => $wallet->name, 'balance' => (BillingPaymentService::cents($wallet->nominal) - BillingPaymentService::cents($future)) / 100];
        })->all();
        $years = BudgetYear::where('school_id', $schoolId)->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)
            ->when($yearId, fn ($q) => $q->whereKey($yearId))->pluck('id');
        $budgets = BudgetPlan::where('school_id', $schoolId)->whereIn('budget_year_id', $years)->where('status', 'approved')->orderBy('id')->get()
            ->reject(fn ($plan) => preg_match('/^(bos|bop|dak)/i', $plan->source_funding ?? ''))
            ->map(fn ($plan) => ['id' => $plan->id, 'name' => $plan->activity_name ?: $plan->program_name, 'budget' => (float) $plan->amount,
                'spent' => $entries->where('type', 'expense')->where('budget_plan_id', $plan->id)->sum('kredit')])->values()->all();
        $validation = $this->closing->validate($schoolId, $type, $period, true);
        $existing = BookClosing::where('school_id', $schoolId)->whereIn('type', ['monthly', 'yearly'])->where('status', 'closed')->get();
        $overlap = $existing->contains(function ($item) use ($schoolId, $start, $end, $type) {
            [$a, $b] = $item->period_start
                ? [$item->period_start, $item->period_end]
                : $this->periods->bounds($schoolId, $item->type, $item->period);

            // An annual close may consolidate already closed months.
            return $a <= $end && $b >= $start && ! ($type === 'yearly' && $item->type === 'monthly');
        });
        if ($overlap) {
            $validation['error_messages'][] = 'Periode ini sudah ditutup atau berada dalam periode yang terkunci.';
        }
        $validation['all_passed'] = $validation['error_messages'] === [];
        $summary = ['collected' => $entries->where('type', '!=', 'transfer')->sum('debet'), 'spent' => $entries->where('type', '!=', 'transfer')->sum('kredit'),
            'account_balance' => collect($accounts)->sum('balance'), 'budget' => collect($budgets)->sum('budget')];
        $data = ['book' => 'komite', 'type' => $type, 'period' => $period, 'start' => $start, 'end' => $end,
            'entries' => $entries->map(fn ($entry) => [...$entry, 'date' => $entry['date']->toDateString()])->all(),
            'accounts' => $accounts, 'wallets' => $wallets, 'budgets' => $budgets, 'summary' => $summary, 'validation' => $validation];
        $data['fingerprint'] = hash('sha256', json_encode($data));

        return $data;
    }

    public function close(int $schoolId, string $type, string $period, int $actorId, string $fingerprint): BookClosing
    {
        return DB::transaction(function () use ($schoolId, $type, $period, $actorId, $fingerprint) {
            DB::table('schools')->where('id', $schoolId)->lockForUpdate()->first();
            $preview = $this->preview($schoolId, $type, $period);
            if (! hash_equals($preview['fingerprint'], $fingerprint)) {
                throw ValidationException::withMessages(['closing' => 'Data berubah sejak pemeriksaan. Cek kesiapan dan tinjau laporan kembali.']);
            }
            if (! $preview['validation']['all_passed']) {
                throw ValidationException::withMessages(['closing' => implode(' ', $preview['validation']['error_messages'])]);
            }
            $closing = $this->closing->close($schoolId, $type, $period, $actorId, true);
            $closing->update(['snapshot' => [...$closing->snapshot, ...$preview], 'audit_metadata' => [...$closing->audit_metadata, 'review_fingerprint' => $fingerprint]]);

            return $closing;
        });
    }
}
