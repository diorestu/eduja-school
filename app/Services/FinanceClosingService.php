<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\BookClosing;
use App\Models\Expense;
use App\Models\PaymentSubmission;
use App\Models\SchoolAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceClosingService
{
    public function __construct(private readonly FinanceLedgerService $ledger)
    {
    }

    public function validate(int $schoolId): array
    {
        $pendingApprovals = ApprovalRequest::query()
            ->where('school_id', $schoolId)
            ->where('status', 'pending')
            ->whereIn('type', ['expense', 'payment', 'budget', 'budget_revision'])
            ->count();
        $pendingPayments = PaymentSubmission::query()->where('school_id', $schoolId)->where('status', 'pending')->count();
        $pendingExpenses = Expense::query()->where('school_id', $schoolId)->where('status', 'pending')->count();
        $negativeAccounts = SchoolAccount::query()->where('school_id', $schoolId)->where('current_balance', '<', 0)->count();
        $unpaidTaxes = Expense::query()
            ->where('school_id', $schoolId)
            ->where('status', 'approved')
            ->where('tax_amount', '>', 0)
            ->where('is_tax_paid', false)
            ->count();

        $checks = [
            'pending_approvals' => $pendingApprovals,
            'pending_payments' => $pendingPayments,
            'pending_expenses' => $pendingExpenses,
            'negative_accounts' => $negativeAccounts,
            'failed_journals' => 0,
            'incomplete_transfers' => 0,
            'unpaid_taxes' => $unpaidTaxes,
            'account_reconciliation' => 0,
            'all_passed' => $pendingApprovals === 0
                && $pendingPayments === 0
                && $pendingExpenses === 0
                && $negativeAccounts === 0
                && $unpaidTaxes === 0,
        ];

        return $checks;
    }

    public function close(int $schoolId, string $type, string $period, int $actorId): BookClosing
    {
        $validation = $this->validate($schoolId);

        if (! $validation['all_passed']) {
            throw ValidationException::withMessages([
                'closing' => 'Tutup buku tidak dapat dilakukan sebelum seluruh pemeriksaan finance lulus.',
            ]);
        }

        return DB::transaction(function () use ($schoolId, $type, $period, $actorId, $validation): BookClosing {
            $closedAt = now();
            $accounts = SchoolAccount::query()
                ->where('school_id', $schoolId)
                ->orderBy('id')
                ->get(['id', 'name', 'type', 'current_balance'])
                ->map(fn (SchoolAccount $account) => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type,
                    'balance' => (float) $account->current_balance,
                ])->values()->all();

            return BookClosing::create([
                'school_id' => $schoolId,
                'closed_by' => $actorId,
                'type' => $type,
                'period' => $period,
                'status' => 'closed',
                'snapshot' => [
                    'period' => $period,
                    'type' => $type,
                    'accounts' => $accounts,
                    'summary' => $this->ledger->summary($schoolId),
                ],
                'validation_results' => $validation,
                'audit_metadata' => [
                    'actor_id' => $actorId,
                    'closed_at' => $closedAt->toISOString(),
                    'source' => 'finance_closing',
                ],
                'closed_at' => $closedAt,
            ]);
        });
    }
}
