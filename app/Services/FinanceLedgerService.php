<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\FinanceIncome;
use App\Models\Invoice;
use App\Models\SchoolAccount;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class FinanceLedgerService
{
    public function entries(int $schoolId, array $filters = []): Collection
    {
        $entries = collect();

        Transaction::query()
            ->with('invoice.student')
            ->whereIn('status', ['approved', 'confirmed'])
            ->when($filters['source_funding'] ?? null, fn ($query, $source) => $query->whereRaw('LOWER(?) = ?', [$source, 'komite']))
            ->whereHas('invoice', fn ($query) => $query->where('school_id', $schoolId))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('payment_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('payment_date', '<=', $to))
            ->when($filters['account_id'] ?? null, fn ($query, $accountId) => $query->where('account_id', $accountId))
            ->get()
            ->each(function (Transaction $transaction) use ($entries, $schoolId): void {
                $entries->push([
                    'id' => $transaction->id,
                    'type' => 'spp',
                    'school_id' => $schoolId,
                    'date' => $transaction->payment_date,
                    'ref' => $transaction->receipt_number,
                    'description' => 'Penerimaan SPP - '.($transaction->invoice->student->name ?? 'Siswa'),
                    'amount' => (float) $transaction->amount_paid,
                    'debet' => (float) $transaction->amount_paid,
                    'kredit' => 0.0,
                    'source_funding' => 'Komite',
                    'payment_method' => $transaction->payment_method,
                    'account_id' => $transaction->account_id,
                ]);
            });

        FinanceIncome::query()
            ->where('school_id', $schoolId)
            ->where('status', 'approved')
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('received_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('received_date', '<=', $to))
            ->when($filters['source_funding'] ?? null, fn ($query, $source) => $query->where('source_funding', $source))
            ->when($filters['account_id'] ?? null, fn ($query, $accountId) => $query->where('account_id', $accountId))
            ->get()
            ->each(function (FinanceIncome $income) use ($entries): void {
                $entries->push([
                    'id' => $income->id,
                    'type' => 'income',
                    'school_id' => $income->school_id,
                    'date' => $income->received_date,
                    'ref' => 'INC-'.$income->id,
                    'description' => 'Penerimaan '.$income->source_funding,
                    'amount' => (float) $income->amount,
                    'debet' => (float) $income->amount,
                    'kredit' => 0.0,
                    'source_funding' => $income->source_funding,
                    'payment_method' => $income->payment_method,
                    'account_id' => $income->account_id,
                ]);
            });

        Expense::query()
            ->where('school_id', $schoolId)
            ->where('status', 'approved')
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('transaction_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('transaction_date', '<=', $to))
            ->when($filters['source_funding'] ?? null, fn ($query, $source) => $query->where('source_funding', $source))
            ->when($filters['account_id'] ?? null, fn ($query, $accountId) => $query->where('account_id', $accountId))
            ->get()
            ->each(function (Expense $expense) use ($entries): void {
                $entries->push([
                    'id' => $expense->id,
                    'type' => 'expense',
                    'school_id' => $expense->school_id,
                    'date' => $expense->transaction_date,
                    'ref' => $expense->reference_invoice ?: 'EXP-'.$expense->id,
                    'description' => $expense->expense_name.' ('.$expense->source_funding.')',
                    'amount' => (float) $expense->amount,
                    'debet' => 0.0,
                    'kredit' => (float) $expense->amount,
                    'source_funding' => $expense->source_funding,
                    'payment_method' => $expense->payment_method,
                    'account_id' => $expense->account_id,
                ]);
            });

        \App\Models\FinanceAccountTransfer::where('school_id', $schoolId)
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('transfer_date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('transfer_date', '<=', $date))
            ->get()->each(function ($transfer) use ($entries, $filters) {
                foreach ([$transfer->from_account_id => false, $transfer->to_account_id => true] as $id => $incoming) {
                    $account = \App\Models\SchoolAccount::find($id);
                    if (($filters['account_id'] ?? null) && (int) $filters['account_id'] !== $id) { continue; }
                    if (($filters['source_funding'] ?? null) && strcasecmp($filters['source_funding'], $account?->source_funding ?? '') !== 0) { continue; }
                    $entries->push(['id' => $transfer->id, 'type' => 'transfer', 'school_id' => $transfer->school_id,
                        'date' => $transfer->transfer_date, 'ref' => 'TRF-'.$transfer->id,
                        'description' => 'Transfer antar rekening '.($incoming ? 'masuk' : 'keluar'), 'amount' => (float) $transfer->amount,
                        'debet' => $incoming ? (float) $transfer->amount : 0.0, 'kredit' => $incoming ? 0.0 : (float) $transfer->amount,
                        'source_funding' => $account?->source_funding ?? '', 'payment_method' => in_array(strtolower($account?->type ?? ''), ['cash', 'tunai']) ? 'Tunai' : 'Transfer', 'account_id' => $id]);
                }
            });

        return $entries
            ->sortBy(fn (array $entry) => $entry['date']->format('Y-m-d'))
            ->values();
    }

    public function summary(?int $schoolId = null): array
    {
        if (! $schoolId) {
            return [
                'collected' => 0.0,
                'spent' => 0.0,
                'outstanding' => 0.0,
                'cash_balance' => 0.0,
                'account_balance' => 0.0,
            ];
        }

        $entries = $this->entries($schoolId);
        $collected = (float) $entries->where('type', '!=', 'transfer')->sum('debet');
        $spent = (float) $entries->where('type', '!=', 'transfer')->sum('kredit');
        $sppCollected = (float) $entries->where('type', 'spp')->sum('amount');
        $invoiced = (float) Invoice::query()->where('school_id', $schoolId)->sum('total_amount');

        return [
            'collected' => $collected,
            'spent' => $spent,
            'outstanding' => max(0, $invoiced - $sppCollected),
            'cash_balance' => (float) SchoolAccount::where('school_id', $schoolId)->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(type)'), ['cash', 'tunai'])->sum('current_balance'),
            'account_balance' => (float) SchoolAccount::where('school_id', $schoolId)->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(type)'), ['bank', 'bos'])->sum('current_balance'),
            'total_balance' => (float) SchoolAccount::where('school_id', $schoolId)->sum('current_balance'),
        ];
    }

    public function bku(int $schoolId, array $filters = []): array
    {
        $entries = $this->entries($schoolId, $filters);
        $taxEntries = $this->taxEntries($schoolId, $filters);
        $opening = $this->openingBalances($schoolId, $filters);
        $ledger = $this->withRunningBalance($entries, $opening['total']);
        $bukuKas = $this->withRunningBalance($entries->filter(fn (array $entry) => mb_strtolower((string) $entry['payment_method']) === 'tunai')->values(), $opening['cash']);
        $bukuBank = $this->withRunningBalance($entries->filter(fn (array $entry) => mb_strtolower((string) $entry['payment_method']) !== 'tunai')->values(), $opening['bank']);
        $bukuPajak = $this->withRunningBalance($taxEntries);

        return [
            'ledger' => $ledger,
            'bukuKas' => $bukuKas,
            'bukuBank' => $bukuBank,
            'bukuPajak' => $bukuPajak,
            'totalDebet' => (float) $ledger->sum('debet'),
            'totalKredit' => (float) $ledger->sum('kredit'),
            'openingBalance' => $opening['total'],
            'finalBalance' => (float) ($ledger->last()['saldo'] ?? $opening['total']),
            'balanceKas' => (float) ($bukuKas->last()['saldo'] ?? $opening['cash']),
            'balanceBank' => (float) ($bukuBank->last()['saldo'] ?? $opening['bank']),
            'balancePajak' => (float) ($bukuPajak->last()['saldo'] ?? 0),
        ];
    }

    private function openingBalances(int $schoolId, array $filters): array
    {
        $accounts = SchoolAccount::where('school_id', $schoolId)->get();
        $source = strtolower($filters['source_funding'] ?? '');
        $accounts = $accounts->filter(fn ($account) => (! ($filters['account_id'] ?? null) || (int) $account->id === (int) $filters['account_id'])
            && (! $source || ($source === 'komite' && (! $account->source_funding || strtolower($account->source_funding) === 'komite'))
                || ($source === 'bos' && strtolower($account->source_funding ?? '') === 'bos') || strtolower($account->source_funding ?? '') === $source));
        $from = $filters['from'] ?? null;
        $snapshot = $from ? \App\Models\BookClosing::where('school_id', $schoolId)->where('status', 'closed')->get()
            ->filter(fn ($closing) => isset($closing->snapshot['next_opening']['date']) && $closing->snapshot['next_opening']['date'] <= $from)
            ->sortByDesc(fn ($closing) => $closing->snapshot['next_opening']['date'])->first()?->snapshot['next_opening'] : null;
        $balances = $accounts->mapWithKeys(function ($account) use ($snapshot) {
            $saved = collect($snapshot['accounts'] ?? [])->firstWhere('id', $account->id);
            return [$account->id => $saved ? (float) $saved['balance'] : (float) $account->opening_balance];
        });
        if ($from) {
            $earlier = $this->entries($schoolId, array_diff_key($filters, ['from' => true, 'to' => true]))
                ->filter(fn ($entry) => $entry['date']->toDateString() < $from && (! $snapshot || $entry['date']->toDateString() >= $snapshot['date']));
            foreach ($earlier as $entry) {
                if ($balances->has($entry['account_id'])) {
                    $balances[$entry['account_id']] += $entry['debet'] - $entry['kredit'];
                }
            }
        }
        $cashIds = $accounts->filter(fn ($account) => in_array(strtolower($account->type), ['tunai', 'cash'], true))->pluck('id')->all();
        return ['total' => (float) $balances->sum(), 'cash' => (float) $balances->only($cashIds)->sum(), 'bank' => (float) $balances->except($cashIds)->sum()];
    }

    private function taxEntries(int $schoolId, array $filters): Collection
    {
        $entries = collect();

        Expense::query()
            ->where('school_id', $schoolId)
            ->where('status', 'approved')
            ->where('tax_amount', '>', 0)
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('transaction_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('transaction_date', '<=', $to))
            ->get()
            ->each(function (Expense $expense) use ($entries): void {
                $ref = $expense->reference_invoice ?: 'EXP-'.$expense->id;
                $entries->push([
                    'date' => $expense->transaction_date,
                    'ref' => $ref.'-TAX',
                    'description' => 'Penerimaan Pajak '.$expense->tax_type.' - '.$expense->expense_name,
                    'debet' => (float) $expense->tax_amount,
                    'kredit' => 0.0,
                ]);

                if ($expense->is_tax_paid) {
                    $entries->push([
                        'date' => $expense->transaction_date,
                        'ref' => $ref.'-SSP',
                        'description' => 'Penyetoran Pajak '.$expense->tax_type.' - '.$expense->expense_name,
                        'debet' => 0.0,
                        'kredit' => (float) $expense->tax_amount,
                    ]);
                }
            });

        return $entries->sortBy(fn (array $entry) => $entry['date']->format('Y-m-d'))->values();
    }

    private function withRunningBalance(Collection $entries, float $balance = 0.0): Collection
    {

        return $entries->map(function (array $entry) use (&$balance): array {
            $balance += $entry['debet'] - $entry['kredit'];
            $entry['saldo'] = $balance;

            return $entry;
        })->values();
    }
}
