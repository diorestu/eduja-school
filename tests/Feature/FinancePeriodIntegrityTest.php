<?php

use App\Models\{ApprovalRequest, BookClosing, BudgetPlan, BudgetYear, Expense, FinanceIncome, SchoolAccount, User, VirtualWallet};
use App\Services\{FinanceClosingService, FinanceLedgerService, FinanceService, ExpensePostingService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function periodFinanceFixture(): array
{
    $school = DB::table('schools')->insertGetId(['name' => 'Period integrity school', 'level' => 'sma', 'ownership' => 'swasta', 'status' => 'active', 'is_active' => true]);
    $user = User::factory()->create(['role' => 'kepsek']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $user->id, 'role' => 'kepsek', 'is_active' => true, 'membership_status' => 'active']);
    $account = app(FinanceService::class)->createAccount($school, ['name' => 'Kas', 'type' => 'Tunai', 'opening_balance' => 1000000]);

    return [$school, $user, $account];
}

it('rejects postings and corrections on either side of a closed period without changing money', function () {
    [$school, $user, $account] = periodFinanceFixture();
    $income = app(FinanceService::class)->recordBosIncome($school, ['source_funding' => 'BOS Reguler', 'amount' => 100, 'received_date' => '2026-07-10', 'account_id' => $account->id]);
    BookClosing::create(['school_id' => $school, 'closed_by' => $user->id, 'type' => 'monthly', 'period' => '2026-07', 'status' => 'closed', 'closed_at' => now()]);
    expect(fn () => app(FinanceService::class)->recordBosIncome($school, ['source_funding' => 'BOS Reguler', 'amount' => 200, 'received_date' => '2026-07-12', 'account_id' => $account->id]))->toThrow(ValidationException::class);
    expect(fn () => $income->update(['received_date' => '2026-08-01']))->toThrow(ValidationException::class);
    expect(fn () => $income->delete())->toThrow(ValidationException::class);
    expect($account->fresh()->current_balance)->toBe('1000100.00');
});

it('scopes closing to its period and carries immutable account and wallet balances into the next opening', function () {
    [$school, $user, $account] = periodFinanceFixture();
    $wallet = VirtualWallet::create(['school_id' => $school, 'name' => 'Operasional', 'source' => 'komite', 'status' => 'active', 'nominal' => 200]);
    FinanceIncome::create(['school_id' => $school, 'source_funding' => 'lainnya', 'amount' => 400, 'received_date' => '2026-08-01', 'account_id' => $account->id, 'status' => 'pending']);
    $closing = app(FinanceClosingService::class)->close($school, 'monthly', '2026-07', $user->id);
    expect($closing->snapshot['wallets'][0]['balance'])->toEqual(200)
        ->and($closing->snapshot['next_opening']['date'])->toBe('2026-08-01')
        ->and($closing->snapshot['next_opening']['accounts'][0]['balance'])->toEqual(1000000);
    $wallet->update(['nominal' => 500]);
    expect($closing->fresh()->snapshot['wallets'][0]['balance'])->toEqual(200);
    expect(fn () => app(FinanceClosingService::class)->close($school, 'monthly', '2026-07', $user->id))->toThrow(ValidationException::class);
});

it('rejects missing proof pending income revisions and account reconciliation differences', function () {
    [$school, $user, $account] = periodFinanceFixture();
    $income = app(FinanceService::class)->recordBosIncome($school, ['source_funding' => 'BOS Reguler', 'amount' => 100, 'received_date' => '2026-07-10', 'account_id' => $account->id]);
    $revision = App\Models\BosIncomeRevision::create(['school_id' => $school, 'finance_income_id' => $income->id, 'old_data' => ['received_date' => '2026-07-10'], 'new_data' => ['received_date' => '2026-07-10'], 'requested_by' => $user->id, 'fingerprint' => 'test', 'reason' => 'Koreksi', 'status' => 'pending']);
    ApprovalRequest::create(['school_id' => $school, 'requested_by' => $user->id, 'type' => 'bos_income_revision', 'approvable_type' => $revision::class, 'approvable_id' => $revision->id, 'status' => 'pending']);
    $account->increment('current_balance', 50);
    $checks = app(FinanceClosingService::class)->validate($school, 'monthly', '2026-07');
    expect($checks['pending_approvals'])->toBe(1)->and($checks['missing_documents'])->toBe(1)->and($checks['account_reconciliation'])->toBe(1)->and($checks['all_passed'])->toBeFalse();
});

it('allows only an active principal to reopen the latest dependent period and audits the reason', function () {
    [$school, $user] = periodFinanceFixture();
    $closing = app(FinanceClosingService::class)->close($school, 'monthly', '2026-07', $user->id);
    $stranger = User::factory()->create(['role' => 'kepsek']);
    expect(fn () => app(FinanceClosingService::class)->reopen($closing, $stranger, 'Koreksi rekening'))->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
    app(FinanceClosingService::class)->reopen($closing, $user, 'Koreksi rekening');
    expect($closing->fresh()->status)->toBe('reopened')->and($closing->fresh()->audit_metadata['reopened_by'])->toBe($user->id)->and($closing->fresh()->audit_metadata['reopen_reason'])->toBe('Koreksi rekening');
    app(FinanceService::class)->recordBosIncome($school, ['source_funding' => 'BOS Reguler', 'amount' => 100, 'received_date' => '2026-07-10']);
});

it('does not create or revise budgets in a closed year', function () {
    [$school, $user] = periodFinanceFixture();
    $year = BudgetYear::create(['school_id' => $school, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'closed']);
    expect(fn () => app(FinanceService::class)->createBudgetPlan($school, $user->id, ['budget_year_id' => $year->id, 'source_funding' => 'Komite', 'program_name' => 'Program', 'activity_name' => 'ATK', 'amount' => 100]))->toThrow(ValidationException::class);
});

it('checks the approved BOS activity remaining budget again when posting', function () {
    [$school, $user, $account] = periodFinanceFixture();
    $account->update(['type' => 'Bank', 'source_funding' => 'bos']);
    $year = BudgetYear::create(['school_id' => $school, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'active']);
    $plan = BudgetPlan::create(['school_id' => $school, 'budget_year_id' => $year->id, 'source_funding' => 'BOS Reguler', 'program_name' => 'Program', 'activity_name' => 'ATK', 'amount' => 100, 'status' => 'approved']);
    $academic = App\Models\AcademicYear::create(['school_id' => $school, 'year' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true]);
    $attributes = ['academic_year_id' => $academic->id, 'school_id' => $school, 'budget_plan_id' => $plan->id, 'account_id' => $account->id, 'source_funding' => 'BOS Reguler', 'expense_name' => 'ATK', 'amount' => 70, 'payment_method' => 'Transfer', 'transaction_date' => '2026-07-10', 'status' => 'pending'];
    $first = Expense::create($attributes);
    $second = Expense::create($attributes);
    app(ExpensePostingService::class)->post($first, $user->id);
    expect(fn () => app(ExpensePostingService::class)->post($second, $user->id))->toThrow(ValidationException::class);
    expect($account->fresh()->current_balance)->toBe('999930.00')->and($second->fresh()->posted_at)->toBeNull();
});

it('reports actual cash and bank balances including opening money', function () {
    [$school, $user, $cash] = periodFinanceFixture();
    app(FinanceService::class)->createAccount($school, ['name' => 'Bank', 'type' => 'Bank', 'opening_balance' => 250]);
    $summary = app(FinanceLedgerService::class)->summary($school);
    expect($summary['cash_balance'])->toEqual(1000000)->and($summary['account_balance'])->toEqual(250);
});

it('refuses direct edits of an approved budget and preserves its approved amount', function () {
    [$school, $user] = periodFinanceFixture();
    $user->update(['role' => 'bendahara']);
    DB::table('school_user_roles')->where('user_id', $user->id)->update(['role' => 'bendahara']);
    $year = BudgetYear::create(['school_id' => $school, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'active']);
    $plan = BudgetPlan::create(['school_id' => $school, 'budget_year_id' => $year->id, 'source_funding' => 'Komite', 'program_name' => 'Program', 'activity_name' => 'ATK', 'amount' => 100, 'status' => 'approved']);
    $this->actingAs($user)->withSession(['active_school_id' => $school])->put(route('finance.budgets.update', $plan), ['budget_year_id' => $year->id, 'source_funding' => 'Komite', 'program_name' => 'Program', 'activity_name' => 'ATK', 'amount' => 200])->assertSessionHasErrors('amount');
    expect($plan->fresh()->amount)->toBe('100.00');
});

it('excludes committee receipts from BOS filtered journals', function () {
    [$school, $user, $account] = periodFinanceFixture();
    $year = App\Models\AcademicYear::create(['school_id' => $school, 'year' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true]);
    $student = App\Models\Student::create(['school_id' => $school, 'nis' => 'FIN001', 'name' => 'Siswa', 'gender' => 'L', 'status' => 'active', 'is_active' => true]);
    $invoice = App\Models\Invoice::create(['school_id' => $school, 'student_id' => $student->id, 'academic_year_id' => $year->id, 'invoice_number' => 'FIN001', 'due_date' => '2026-07-10', 'total_amount' => 100, 'status' => 'Lunas']);
    App\Models\Transaction::create(['school_id' => $school, 'invoice_id' => $invoice->id, 'account_id' => $account->id, 'amount_paid' => 100, 'payment_date' => '2026-07-10', 'payment_method' => 'Tunai', 'receipt_number' => 'F1', 'status' => 'approved']);
    FinanceIncome::create(['school_id' => $school, 'source_funding' => 'BOS Reguler', 'bos_year' => 2026, 'amount' => 200, 'received_date' => '2026-07-10', 'status' => 'approved']);
    expect(app(FinanceLedgerService::class)->entries($school, ['source_funding' => 'BOS Reguler'])->pluck('type')->all())->toBe(['income']);
});

it('restores the correct historical wallet opening despite later allocations', function () {
    [$school, $user] = periodFinanceFixture();
    $wallet = VirtualWallet::create(['school_id' => $school, 'name' => 'Dompet', 'source' => 'hibah', 'status' => 'active', 'nominal' => 0]);
    $income = FinanceIncome::create(['school_id' => $school, 'source_funding' => 'Hibah', 'amount' => 100, 'received_date' => '2026-07-10', 'status' => 'approved', 'posted_at' => now()]);
    app(App\Services\FinanceWalletService::class)->adjust($wallet, 10000, '2026-07-10', $income);
    app(App\Services\FinanceWalletService::class)->adjust($wallet, 5000, '2026-08-10', $income);
    $closing = app(FinanceClosingService::class)->close($school, 'monthly', '2026-07', $user->id);
    expect($closing->snapshot['wallets'][0]['balance'])->toEqual(100)->and($closing->snapshot['next_opening']['wallets'][0]['balance'])->toEqual(100);
});

it('keeps annual closing status controlled by closing and reopen rather than ordinary master edits', function () {
    [$school, $user] = periodFinanceFixture();
    $user->update(['role' => 'bendahara']);
    DB::table('school_user_roles')->where('user_id', $user->id)->update(['role' => 'bendahara']);
    $year = BudgetYear::create(['school_id' => $school, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'active']);
    $this->actingAs($user)->withSession(['active_school_id' => $school])->put(route('finance.budget-years.update', $year), ['name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'closed'])->assertSessionHasErrors('status');
    expect($year->fresh()->status)->toBe('active');
});

it('starts a filtered cashbook with opening balances instead of resetting to zero', function () {
    [$school] = periodFinanceFixture();
    $book = app(FinanceLedgerService::class)->bku($school, ['from' => '2026-08-01', 'to' => '2026-08-31']);
    expect($book['openingBalance'])->toEqual(1000000)->and($book['balanceKas'])->toEqual(1000000)->and($book['finalBalance'])->toEqual(1000000);
});
