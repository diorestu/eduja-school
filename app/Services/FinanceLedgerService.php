<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\SchoolAccount;
use App\Models\Transaction;

class FinanceLedgerService
{
    public function summary(?int $schoolId = null): array
    {
        $transactions = Transaction::query();
        $expenses = Expense::query()->where('status', 'approved');
        $invoices = Invoice::query();
        $accounts = SchoolAccount::query();

        if ($schoolId) {
            $transactions->whereHas('invoice', fn ($query) => $query->where('school_id', $schoolId));
            $expenses->where('school_id', $schoolId);
            $invoices->where('school_id', $schoolId);
            $accounts->where('school_id', $schoolId);
        }

        $collected = (float) $transactions->sum('amount_paid');
        $spent = (float) $expenses->sum('amount');
        $invoiced = (float) $invoices->sum('total_amount');

        return [
            'collected' => $collected,
            'spent' => $spent,
            'outstanding' => max(0, $invoiced - $collected),
            'cash_balance' => $collected - $spent,
            'account_balance' => (float) $accounts->sum('current_balance'),
        ];
    }
}
