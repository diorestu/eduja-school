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
            ->where('status', 'approved')
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
        $collected = (float) $entries->sum('debet');
        $spent = (float) $entries->sum('kredit');
        $sppCollected = (float) $entries->where('type', 'spp')->sum('amount');
        $invoiced = (float) Invoice::query()->where('school_id', $schoolId)->sum('total_amount');

        return [
            'collected' => $collected,
            'spent' => $spent,
            'outstanding' => max(0, $invoiced - $sppCollected),
            'cash_balance' => $collected - $spent,
            'account_balance' => (float) SchoolAccount::query()->where('school_id', $schoolId)->sum('current_balance'),
        ];
    }

    public function bku(int $schoolId, array $filters = []): array
    {
        $entries = $this->entries($schoolId, $filters);
        $taxEntries = $this->taxEntries($schoolId, $filters);
        $ledger = $this->withRunningBalance($entries);
        $bukuKas = $this->withRunningBalance($entries->filter(fn (array $entry) => mb_strtolower((string) $entry['payment_method']) === 'tunai')->values());
        $bukuBank = $this->withRunningBalance($entries->filter(fn (array $entry) => mb_strtolower((string) $entry['payment_method']) !== 'tunai')->values());
        $bukuPajak = $this->withRunningBalance($taxEntries);

        return [
            'ledger' => $ledger,
            'bukuKas' => $bukuKas,
            'bukuBank' => $bukuBank,
            'bukuPajak' => $bukuPajak,
            'totalDebet' => (float) $ledger->sum('debet'),
            'totalKredit' => (float) $ledger->sum('kredit'),
            'finalBalance' => (float) ($ledger->last()['saldo'] ?? 0),
            'balanceKas' => (float) ($bukuKas->last()['saldo'] ?? 0),
            'balanceBank' => (float) ($bukuBank->last()['saldo'] ?? 0),
            'balancePajak' => (float) ($bukuPajak->last()['saldo'] ?? 0),
        ];
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

    private function withRunningBalance(Collection $entries): Collection
    {
        $balance = 0.0;

        return $entries->map(function (array $entry) use (&$balance): array {
            $balance += $entry['debet'] - $entry['kredit'];
            $entry['saldo'] = $balance;

            return $entry;
        })->values();
    }
}
