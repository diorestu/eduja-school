<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\ApprovalRequest;
use App\Models\BudgetPlan;
use App\Models\BudgetPlanRevision;
use App\Models\BudgetYear;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\FinanceIncome;
use App\Models\FundAllocation;
use App\Models\IncomeType;
use App\Models\PaymentSubmission;
use App\Models\SchoolAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FinanceService
{
    public function createAccount(int $schoolId, array $attributes): SchoolAccount
    {
        $this->assertActiveSchool($schoolId);
        $openingBalance = (float) ($attributes['opening_balance'] ?? 0);

        return SchoolAccount::create([
            'school_id' => $schoolId,
            'name' => $attributes['name'],
            'type' => $attributes['type'] ?? 'Tunai',
            'bank_name' => $attributes['bank_name'] ?? null,
            'account_number' => $attributes['account_number'] ?? null,
            'opening_balance' => $openingBalance,
            'current_balance' => $openingBalance,
            'is_active' => $attributes['is_active'] ?? true,
        ]);
    }

    public function createIncomeType(int $schoolId, array $attributes): IncomeType
    {
        $this->assertActiveSchool($schoolId);

        return IncomeType::create([
            'school_id' => $schoolId,
            'name' => $attributes['name'],
            'code' => $attributes['code'] ?? $this->nextCode(IncomeType::class, $schoolId, 'INC'),
            'category' => $attributes['category'] ?? 'komite',
            'uses_allocation' => (bool) ($attributes['uses_allocation'] ?? false),
            'requires_approval' => (bool) ($attributes['requires_approval'] ?? false),
        ]);
    }

    public function createExpenseType(int $schoolId, array $attributes): ExpenseType
    {
        $this->assertActiveSchool($schoolId);

        return ExpenseType::create([
            'school_id' => $schoolId,
            'name' => $attributes['name'],
            'code' => $attributes['code'] ?? $this->nextCode(ExpenseType::class, $schoolId, 'EXP'),
            'source_funding' => $attributes['source_funding'] ?? 'komite',
            'bos_component' => $attributes['bos_component'] ?? null,
            'requires_approval' => (bool) ($attributes['requires_approval'] ?? false),
            'supporting_document_path' => $attributes['supporting_document_path'] ?? null,
        ]);
    }

    public function createFundAllocation(int $schoolId, array $attributes): FundAllocation
    {
        $this->assertActiveSchool($schoolId);
        $incomeType = IncomeType::query()->whereKey($attributes['income_type_id'])->firstOrFail();
        $account = $this->accountForSchool($schoolId, (int) $attributes['account_id']);

        if ($incomeType->school_id !== $schoolId) {
            throw new InvalidArgumentException('Jenis pemasukan tidak berada pada sekolah aktif.');
        }

        return FundAllocation::create([
            'school_id' => $schoolId,
            'income_type_id' => $incomeType->id,
            'account_id' => $account->id,
            'name' => $attributes['name'],
            'method' => $attributes['method'] ?? 'nominal',
            'amount' => $attributes['amount'] ?? 0,
            'status' => $attributes['status'] ?? 'active',
        ]);
    }

    public function recordBosIncome(int $schoolId, array $attributes): FinanceIncome
    {
        $this->assertActiveSchool($schoolId);
        $account = isset($attributes['account_id'])
            ? $this->accountForSchool($schoolId, (int) $attributes['account_id'])
            : null;

        return DB::transaction(function () use ($schoolId, $attributes, $account): FinanceIncome {
            $income = FinanceIncome::create([
                'school_id' => $schoolId,
                'income_type_id' => $attributes['income_type_id'] ?? null,
                'account_id' => $account?->id,
                'source_funding' => $attributes['source_funding'],
                'bos_year' => $attributes['bos_year'] ?? null,
                'amount' => $attributes['amount'],
                'received_date' => $attributes['received_date'],
                'payment_method' => $attributes['payment_method'] ?? null,
                'proof_path' => $attributes['proof_path'] ?? null,
                'status' => 'approved',
                'posted_at' => now(),
            ]);

            $account?->increment('current_balance', $income->amount);

            return $income;
        });
    }

    public function createExpense(int $schoolId, int $requestedBy, array $attributes): Expense
    {
        $this->assertActiveSchool($schoolId);
        $account = isset($attributes['account_id'])
            ? $this->accountForSchool($schoolId, (int) $attributes['account_id'])
            : null;

        if (isset($attributes['academic_year_id']) && ! AcademicYear::query()
            ->whereKey($attributes['academic_year_id'])
            ->where('school_id', $schoolId)
            ->exists()) {
            throw new InvalidArgumentException('Tahun akademik tidak berada pada sekolah aktif.');
        }

        return DB::transaction(function () use ($schoolId, $requestedBy, $attributes, $account): Expense {
            $expense = Expense::create([
                ...$attributes,
                'school_id' => $schoolId,
                'account_id' => $account?->id,
                'status' => 'pending',
                'tax_amount' => $attributes['tax_amount'] ?? 0,
                'is_tax_paid' => (bool) ($attributes['is_tax_paid'] ?? false),
            ]);

            ApprovalRequest::create([
                'school_id' => $schoolId,
                'requested_by' => $requestedBy,
                'approvable_type' => Expense::class,
                'approvable_id' => $expense->id,
                'type' => 'expense',
                'status' => 'pending',
            ]);

            return $expense;
        });
    }

    public function createBudgetYear(int $schoolId, array $attributes): BudgetYear
    {
        $this->assertActiveSchool($schoolId);

        return BudgetYear::create([
            'school_id' => $schoolId,
            'name' => $attributes['name'],
            'start_date' => $attributes['start_date'],
            'end_date' => $attributes['end_date'],
            'status' => 'active',
        ]);
    }

    public function createBudgetPlan(int $schoolId, int $requestedBy, array $attributes): BudgetPlan
    {
        $this->assertActiveSchool($schoolId);
        $year = BudgetYear::query()->whereKey($attributes['budget_year_id'])->firstOrFail();

        if ($year->school_id !== $schoolId) {
            throw new InvalidArgumentException('Tahun anggaran tidak berada pada sekolah aktif.');
        }

        return DB::transaction(function () use ($schoolId, $requestedBy, $attributes): BudgetPlan {
            $plan = BudgetPlan::create([
                ...$attributes,
                'school_id' => $schoolId,
                'status' => 'pending',
            ]);

            $this->createApproval($schoolId, $requestedBy, BudgetPlan::class, $plan->id, 'budget');

            return $plan;
        });
    }

    public function reviseBudgetPlan(int $schoolId, int $requestedBy, int $budgetPlanId, array $attributes): BudgetPlanRevision
    {
        $this->assertActiveSchool($schoolId);
        $plan = BudgetPlan::query()->whereKey($budgetPlanId)->firstOrFail();

        if ($plan->school_id !== $schoolId) {
            throw new InvalidArgumentException('Rencana anggaran tidak berada pada sekolah aktif.');
        }

        return DB::transaction(function () use ($schoolId, $requestedBy, $plan, $attributes): BudgetPlanRevision {
            $revision = BudgetPlanRevision::create([
                'school_id' => $schoolId,
                'budget_plan_id' => $plan->id,
                'old_amount' => $plan->amount,
                'new_amount' => $attributes['new_amount'],
                'reason' => $attributes['reason'],
                'requested_by' => $requestedBy,
                'status' => 'pending',
            ]);

            $this->createApproval($schoolId, $requestedBy, BudgetPlanRevision::class, $revision->id, 'budget_revision');

            return $revision;
        });
    }

    public function applyApproval(ApprovalRequest $approval, Model $approvable, string $status, int $reviewerId): void
    {
        if ($approvable instanceof Expense && $status === 'approved') {
            $this->postExpense($approvable, $reviewerId);
        }

        if ($approvable instanceof PaymentSubmission && $status === 'approved') {
            $this->postPayment($approvable, $reviewerId);
        }

        if ($approvable instanceof BudgetPlan && $status === 'approved') {
            $approvable->forceFill([
                'approved_by' => $reviewerId,
                'approved_at' => now(),
            ])->save();
        }

        if ($approvable instanceof BudgetPlanRevision) {
            $approvable->forceFill([
                'status' => $status,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ])->save();

            if ($status === 'approved') {
                $approvable->budgetPlan()->update(['amount' => $approvable->new_amount]);
            }
        }
    }

    public function assertApprovalScope(int $schoolId, Model $approvable): void
    {
        if (isset($approvable->school_id) && (int) $approvable->school_id !== $schoolId) {
            throw new InvalidArgumentException('Approval menunjuk data sekolah lain.');
        }

        if (($approvable instanceof Expense || $approvable instanceof PaymentSubmission) && $approvable->account_id) {
            $this->accountForSchool($schoolId, (int) $approvable->account_id);
        }
    }

    private function postExpense(Expense $expense, int $reviewerId): void
    {
        if ($expense->posted_at) {
            return;
        }

        DB::transaction(function () use ($expense, $reviewerId): void {
            if ($expense->account_id) {
                SchoolAccount::query()->whereKey($expense->account_id)->decrement('current_balance', $expense->amount);
            }

            $expense->forceFill([
                'posted_at' => now(),
                'posted_by' => $reviewerId,
            ])->save();
        });
    }

    private function postPayment(PaymentSubmission $payment, int $reviewerId): void
    {
        if ($payment->posted_at) {
            return;
        }

        DB::transaction(function () use ($payment, $reviewerId): void {
            if ($payment->account_id) {
                SchoolAccount::query()->whereKey($payment->account_id)->increment('current_balance', $payment->amount);
            }

            $payment->forceFill([
                'posted_at' => now(),
                'posted_by' => $reviewerId,
            ])->save();
        });
    }

    private function createApproval(int $schoolId, int $requestedBy, string $type, int $id, string $approvalType): ApprovalRequest
    {
        return ApprovalRequest::create([
            'school_id' => $schoolId,
            'requested_by' => $requestedBy,
            'approvable_type' => $type,
            'approvable_id' => $id,
            'type' => $approvalType,
            'status' => 'pending',
        ]);
    }

    private function accountForSchool(int $schoolId, int $accountId): SchoolAccount
    {
        $account = SchoolAccount::query()->whereKey($accountId)->firstOrFail();

        if ((int) $account->school_id !== $schoolId) {
            throw new InvalidArgumentException('Akun tidak berada pada sekolah aktif.');
        }

        return $account;
    }

    private function nextCode(string $model, int $schoolId, string $prefix): string
    {
        $next = $model::query()->where('school_id', $schoolId)->count() + 1;

        return $prefix.'-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    private function assertActiveSchool(int $schoolId): void
    {
        $activeSchoolId = session('active_school_id');

        if ($activeSchoolId !== null && (int) $activeSchoolId !== $schoolId) {
            throw new InvalidArgumentException('Finance hanya dapat diproses pada sekolah aktif.');
        }
    }
}
