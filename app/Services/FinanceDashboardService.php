<?php

namespace App\Services;

use App\Models\BudgetPlan;
use App\Models\Expense;
use App\Models\FinanceIncome;
use App\Models\Invoice;
use App\Models\SchoolAccount;
use App\Models\Transaction;
use App\Models\VirtualWallet;
use Illuminate\Support\Collection;

class FinanceDashboardService
{
    public function forSchool(int $schoolId): array
    {
        $start = now()->startOfMonth();
        $end = $start->copy()->addMonth();
        $year = now()->year;
        $receipts = Transaction::whereHas('invoice', fn ($q) => $q->where('school_id', $schoolId))->whereIn('status', ['approved', 'confirmed']);
        $incomes = FinanceIncome::where('school_id', $schoolId)->whereIn('status', ['approved', 'confirmed']);
        $expenses = Expense::where('school_id', $schoolId)->where('status', 'approved');
        $incomeMonth = (float) (clone $receipts)->where('payment_date', '>=', $start)->where('payment_date', '<', $end)->sum('amount_paid')
            + (float) (clone $incomes)->where('received_date', '>=', $start)->where('received_date', '<', $end)->sum('amount');
        $expenseMonth = (float) (clone $expenses)->where('transaction_date', '>=', $start)->where('transaction_date', '<', $end)->sum('amount');
        $accounts = SchoolAccount::where('school_id', $schoolId)->where('is_active', true)->get();
        $wallets = VirtualWallet::where('school_id', $schoolId)->whereIn('status', ['active', 'Aktif'])->orderByDesc('nominal')->get();
        $walletTotal = (float) $wallets->sum('nominal');
        $walletShares = $wallets->map(fn ($wallet) => ['name' => $wallet->name, 'amount' => (float) $wallet->nominal,
            'percentage' => $walletTotal > 0 ? round(max(0, (float) $wallet->nominal) / $walletTotal * 100, 1) : 0]);
        $budgets = [];
        foreach (['komite', 'bos'] as $source) {
            $plans = BudgetPlan::where('school_id', $schoolId)->where('status', 'approved')
                ->whereHas('budgetYear', fn ($q) => $q->where('school_id', $schoolId)->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now()))
                ->where(function ($query) use ($source) {
                    if ($source === 'komite') {
                        $query->whereRaw('LOWER(source_funding) = ?', ['komite']);
                    } else {
                        $query->whereRaw('LOWER(source_funding) LIKE ?', ['bos%'])->orWhereRaw('LOWER(source_funding) LIKE ?', ['bop%'])->orWhereRaw('LOWER(source_funding) LIKE ?', ['dak%']);
                    }
                })->get();
            $total = (float) $plans->sum('amount');
            $spent = (float) (clone $expenses)->whereIn('budget_plan_id', $plans->pluck('id'))->sum('amount');
            $budgets[$source] = ['amount' => $total, 'spent' => $spent, 'remaining' => $total - $spent, 'percentage' => $total > 0 ? round($spent / $total * 100, 1) : null];
        }
        $invoices = Invoice::where('school_id', $schoolId)->with(['student.schoolClasses.academicYear', 'billingItem', 'invoiceItems',
            'transactions' => fn ($q) => $q->whereIn('status', ['approved', 'confirmed'])])->get();
        $collection = fn ($group) => $this->rate($group);
        $byClass = $invoices->groupBy(function ($invoice) use ($schoolId) {
            $classes = $invoice->student?->schoolClasses->where('school_id', $schoolId) ?? collect();
            $class = $classes->first(fn ($class) => $class->academicYear?->is_active) ?? $classes->sortByDesc('academic_year_id')->first();

            return $class?->name ?? 'Tanpa kelas';
        })->map(fn ($group, $name) => ['name' => $name, ...$collection($group)])->values();
        $byBill = $invoices->groupBy(fn ($invoice) => $invoice->billing_item_id ? 'bill-'.$invoice->billing_item_id : 'legacy-'.($invoice->invoiceItems->first()?->name ?? 'Tagihan lainnya'))
            ->map(fn ($group) => ['name' => $group->first()->billingItem?->name ?? $group->first()->invoiceItems->first()?->name ?? 'Tagihan lainnya', ...$collection($group)])->values();
        $trend = ['months' => [], 'current' => [], 'previous' => []];
        foreach (range(1, now()->month) as $month) {
            $trend['months'][] = now()->startOfMonth()->setMonth($month)->locale('id')->translatedFormat('M');
            foreach (['current' => $year, 'previous' => $year - 1] as $key => $trendYear) {
                $cutoff = now()->setDate($trendYear, $month, 1)->endOfMonth();
                $cohort = $invoices->filter(fn ($invoice) => $invoice->due_date?->year === $trendYear && $invoice->due_date->month === $month);
                $trend[$key][] = $cohort->isEmpty() ? null : $this->rate($cohort, $cutoff)['percentage'];
            }
        }

        return ['incomeMonth' => $incomeMonth, 'expenseMonth' => $expenseMonth, 'cashflow' => $incomeMonth - $expenseMonth,
            'bosYear' => (float) (clone $incomes)->bos()->whereYear('received_date', $year)->sum('amount'),
            'cashBalance' => (float) $accounts->filter(fn ($a) => in_array(strtolower($a->type), ['tunai', 'cash']))->sum('current_balance'),
            'bankBalance' => (float) $accounts->filter(fn ($a) => strtolower($a->type) === 'bank')->sum('current_balance'),
            'bosBalance' => (float) $accounts->filter(fn ($a) => preg_match('/^(bos|bop|dak)/i', $a->source_funding ?? ''))->sum('current_balance'),
            'wallets' => $walletShares, 'walletTotal' => $walletTotal, 'budgets' => $budgets, 'byClass' => $byClass, 'byBill' => $byBill, 'trend' => $trend];
    }

    private function rate(Collection $invoices, $cutoff = null): array
    {
        $billed = (float) $invoices->sum('total_amount');
        $paid = (float) $invoices->sum(fn ($invoice) => min((float) $invoice->total_amount, $invoice->transactions
            ->filter(fn ($tx) => ! $cutoff || $tx->payment_date->lte($cutoff))->sum('amount_paid')));

        return ['billed' => $billed, 'paid' => $paid, 'percentage' => $billed > 0 ? round($paid / $billed * 100, 1) : 0];
    }
}
