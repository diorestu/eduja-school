<?php

use App\Models\Expense;
use App\Models\FinanceIncome;
use App\Models\Invoice;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Services\FinanceClosingService;
use App\Services\FinanceLedgerService;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function makeFinanceSchool(string $name): int
{
    return DB::table('schools')->insertGetId([
        'name' => $name,
        'level' => 'sma',
        'ownership' => 'swasta',
        'status' => 'active',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function makeFinanceUser(int $schoolId, string $role = 'bendahara'): User
{
    $user = User::factory()->create(['role' => $role]);

    DB::table('school_user_roles')->insert([
        'user_id' => $user->id,
        'school_id' => $schoolId,
        'role' => $role,
        'is_active' => true,
        'membership_status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user;
}

function makeFinanceAcademicYear(int $schoolId): int
{
    return DB::table('academic_years')->insertGetId([
        'school_id' => $schoolId,
        'year' => '2026/2027',
        'semester' => 'Ganjil',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('creates school accounts, wallets, and finance masters within one school', function () {
    $schoolId = makeFinanceSchool('Sekolah Finance A');
    $otherSchoolId = makeFinanceSchool('Sekolah Finance B');
    $service = app(FinanceService::class);

    $cash = $service->createAccount($schoolId, [
        'name' => 'Kas Bendahara',
        'type' => 'Tunai',
        'opening_balance' => 500000,
    ]);
    $wallet = $service->createAccount($schoolId, [
        'name' => 'Dompet BOS',
        'type' => 'Wallet',
    ]);
    $incomeType = $service->createIncomeType($schoolId, [
        'name' => 'BOS Reguler',
        'category' => 'BOS',
        'uses_allocation' => true,
    ]);
    $expenseType = $service->createExpenseType($schoolId, [
        'name' => 'Belanja ATK BOS',
        'source_funding' => 'BOS',
        'bos_component' => 'Belanja barang',
        'requires_approval' => true,
    ]);
    $allocation = $service->createFundAllocation($schoolId, [
        'income_type_id' => $incomeType->id,
        'name' => 'Operasional',
        'method' => 'nominal',
        'amount' => 250000,
        'account_id' => $wallet->id,
    ]);

    expect($cash->school_id)->toBe($schoolId)
        ->and($wallet->type)->toBe('Wallet')
        ->and($incomeType->school_id)->toBe($schoolId)
        ->and($expenseType->school_id)->toBe($schoolId)
        ->and($allocation->account_id)->toBe($wallet->id)
        ->and(DB::table('school_accounts')->where('school_id', $otherSchoolId)->count())->toBe(0);
});

it('records approved BOS income and posts it to the selected school account', function () {
    $schoolId = makeFinanceSchool('Sekolah BOS');
    $service = app(FinanceService::class);
    $account = $service->createAccount($schoolId, ['name' => 'Rekening BOS', 'type' => 'Bank']);

    $income = $service->recordBosIncome($schoolId, [
        'source_funding' => 'BOS Reguler',
        'bos_year' => 2026,
        'amount' => 12000000,
        'received_date' => '2026-07-01',
        'account_id' => $account->id,
        'proof_path' => 'finance/sp2d.pdf',
    ]);

    expect($income)->toBeInstanceOf(FinanceIncome::class)
        ->and($income->status)->toBe('approved')
        ->and((float) $account->fresh()->current_balance)->toBe(12000000.0);
});

it('creates an expense as pending and does not debit an account before approval', function () {
    $schoolId = makeFinanceSchool('Sekolah Pengeluaran');
    $user = makeFinanceUser($schoolId);
    $academicYearId = makeFinanceAcademicYear($schoolId);
    $service = app(FinanceService::class);
    $account = $service->createAccount($schoolId, ['name' => 'Kas Operasional', 'type' => 'Tunai', 'opening_balance' => 1000000]);

    $expense = $service->createExpense($schoolId, $user->id, [
        'expense_name' => 'Pembelian ATK',
        'amount' => 200000,
        'academic_year_id' => $academicYearId,
        'transaction_date' => '2026-07-04',
        'source_funding' => 'Komite',
        'payment_method' => 'Tunai',
        'account_id' => $account->id,
    ]);

    expect($expense->status)->toBe('pending')
        ->and(DB::table('approval_requests')->where('approvable_type', Expense::class)->where('approvable_id', $expense->id)->value('status'))->toBe('pending')
        ->and((float) $account->fresh()->current_balance)->toBe(1000000.0);
});

it('requires approved budget plans and records revisions as pending approvals', function () {
    $schoolId = makeFinanceSchool('Sekolah Anggaran');
    $user = makeFinanceUser($schoolId);
    $service = app(FinanceService::class);
    $year = $service->createBudgetYear($schoolId, [
        'name' => 'Tahun Anggaran 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $plan = $service->createBudgetPlan($schoolId, $user->id, [
        'budget_year_id' => $year->id,
        'source_funding' => 'BOS',
        'program_name' => 'Operasional Sekolah',
        'activity_name' => 'Belanja ATK',
        'amount' => 1000000,
    ]);
    $revision = $service->reviseBudgetPlan($schoolId, $user->id, $plan->id, [
        'new_amount' => 1250000,
        'reason' => 'Penyesuaian kebutuhan semester berjalan',
    ]);

    expect($plan->status)->toBe('pending')
        ->and($revision->status)->toBe('pending')
        ->and(DB::table('approval_requests')->where('approvable_id', $plan->id)->where('type', 'budget')->exists())->toBeTrue()
        ->and(DB::table('approval_requests')->where('approvable_id', $revision->id)->where('type', 'budget_revision')->exists())->toBeTrue();
});

it('returns only approved entries for the active school and requested date range', function () {
    $schoolId = makeFinanceSchool('Sekolah Ledger A');
    $otherSchoolId = makeFinanceSchool('Sekolah Ledger B');
    $service = app(FinanceService::class);
    $ledger = app(FinanceLedgerService::class);
    $account = $service->createAccount($schoolId, ['name' => 'Kas A', 'type' => 'Tunai']);
    $otherAccount = $service->createAccount($otherSchoolId, ['name' => 'Kas B', 'type' => 'Tunai']);

    $service->recordBosIncome($schoolId, [
        'source_funding' => 'BOS Reguler', 'bos_year' => 2026, 'amount' => 500000,
        'received_date' => '2026-07-10', 'account_id' => $account->id,
    ]);
    $service->recordBosIncome($otherSchoolId, [
        'source_funding' => 'BOS Reguler', 'bos_year' => 2026, 'amount' => 900000,
        'received_date' => '2026-07-10', 'account_id' => $otherAccount->id,
    ]);

    $user = makeFinanceUser($schoolId);
    $academicYearId = makeFinanceAcademicYear($schoolId);
    $pending = $service->createExpense($schoolId, $user->id, [
        'expense_name' => 'Pending', 'amount' => 100000, 'transaction_date' => '2026-07-11',
        'source_funding' => 'BOS', 'payment_method' => 'Tunai', 'account_id' => $account->id, 'academic_year_id' => $academicYearId,
    ]);
    $approved = Expense::query()->findOrFail($pending->id);
    $approved->update(['status' => 'approved']);
    $outsideRange = $service->recordBosIncome($schoolId, [
        'source_funding' => 'Hibah', 'amount' => 700000, 'received_date' => '2026-08-01', 'account_id' => $account->id,
    ]);

    $entries = $ledger->entries($schoolId, ['from' => '2026-07-01', 'to' => '2026-07-31']);

    expect($entries)->toHaveCount(2)
        ->and($entries->pluck('amount')->all())->toEqualCanonicalizing([500000.0, 100000.0])
        ->and($entries->pluck('school_id')->unique()->all())->toBe([$schoolId])
        ->and($entries->pluck('id')->all())->not->toContain($outsideRange->id);
});

it('rejects closing while finance approvals remain pending', function () {
    $schoolId = makeFinanceSchool('Sekolah Closing Gagal');
    $user = makeFinanceUser($schoolId);
    $academicYearId = makeFinanceAcademicYear($schoolId);
    $service = app(FinanceService::class);
    $service->createExpense($schoolId, $user->id, [
        'expense_name' => 'Belum Disetujui', 'amount' => 100000, 'transaction_date' => '2026-07-12',
        'source_funding' => 'BOS', 'payment_method' => 'Tunai', 'academic_year_id' => $academicYearId,
    ]);

    expect(fn () => app(FinanceClosingService::class)->close($schoolId, 'monthly', '2026-07', $user->id))
        ->toThrow(ValidationException::class);
    expect(DB::table('book_closings')->where('school_id', $schoolId)->exists())->toBeFalse();
});

it('stores a closing snapshot and audit metadata after all checks pass', function () {
    $schoolId = makeFinanceSchool('Sekolah Closing Berhasil');
    $user = makeFinanceUser($schoolId);
    $service = app(FinanceService::class);
    $account = $service->createAccount($schoolId, ['name' => 'Kas Closing', 'type' => 'Tunai', 'opening_balance' => 350000]);
    $service->recordBosIncome($schoolId, [
        'source_funding' => 'BOS Reguler', 'bos_year' => 2026, 'amount' => 650000,
        'received_date' => '2026-07-10', 'account_id' => $account->id,
    ]);

    $closing = app(FinanceClosingService::class)->close($schoolId, 'monthly', '2026-07', $user->id);

    expect($closing->status)->toBe('closed')
        ->and($closing->closed_by)->toBe($user->id)
        ->and($closing->validation_results['all_passed'])->toBeTrue()
        ->and($closing->audit_metadata['actor_id'])->toBe($user->id)
        ->and($closing->snapshot['accounts'][0]['balance'])->toEqual(1000000.0);
});

it('rejects finance writes that reference an account from another school', function () {
    $schoolId = makeFinanceSchool('Sekolah Scope A');
    $otherSchoolId = makeFinanceSchool('Sekolah Scope B');
    $service = app(FinanceService::class);
    $otherAccount = $service->createAccount($otherSchoolId, ['name' => 'Kas B', 'type' => 'Tunai']);

    expect(fn () => $service->recordBosIncome($schoolId, [
        'source_funding' => 'BOS Reguler', 'amount' => 100000, 'received_date' => '2026-07-01', 'account_id' => $otherAccount->id,
    ]))->toThrow(InvalidArgumentException::class);
});

it('posts an approved expense to its account through the existing approval route', function () {
    $schoolId = makeFinanceSchool('Sekolah Approval Expense');
    $bendahara = makeFinanceUser($schoolId);
    $kepsek = makeFinanceUser($schoolId, 'kepsek');
    $academicYearId = makeFinanceAcademicYear($schoolId);
    $service = app(FinanceService::class);
    $account = $service->createAccount($schoolId, ['name' => 'Kas Approval', 'type' => 'Tunai', 'opening_balance' => 1000000]);
    $expense = $service->createExpense($schoolId, $bendahara->id, [
        'academic_year_id' => $academicYearId,
        'expense_name' => 'Belanja Approval',
        'amount' => 200000,
        'transaction_date' => '2026-07-13',
        'source_funding' => 'BOS',
        'payment_method' => 'Tunai',
        'account_id' => $account->id,
    ]);
    $approvalId = DB::table('approval_requests')->where('approvable_id', $expense->id)->value('id');

    $this->actingAs($kepsek)
        ->withSession(['active_school_id' => $schoolId])
        ->post("/approvals/{$approvalId}/approve")
        ->assertRedirect();

    expect((float) $account->fresh()->current_balance)->toBe(800000.0)
        ->and(Expense::findOrFail($expense->id)->posted_at)->not->toBeNull();
});

it('posts an approved payment submission to its account through the existing approval route', function () {
    $schoolId = makeFinanceSchool('Sekolah Approval Payment');
    $bendahara = makeFinanceUser($schoolId);
    $service = app(FinanceService::class);
    $account = $service->createAccount($schoolId, ['name' => 'Bank Komite', 'type' => 'Bank', 'opening_balance' => 500000]);
    $submissionId = DB::table('payment_submissions')->insertGetId([
        'school_id' => $schoolId,
        'submitted_by' => $bendahara->id,
        'account_id' => $account->id,
        'amount' => 150000,
        'payment_date' => '2026-07-13',
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $approvalId = DB::table('approval_requests')->insertGetId([
        'school_id' => $schoolId,
        'requested_by' => $bendahara->id,
        'approvable_type' => PaymentSubmission::class,
        'approvable_id' => $submissionId,
        'type' => 'payment',
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($bendahara)
        ->withSession(['active_school_id' => $schoolId])
        ->post("/approvals/{$approvalId}/approve")
        ->assertRedirect();

    expect((float) $account->fresh()->current_balance)->toBe(650000.0)
        ->and(PaymentSubmission::findOrFail($submissionId)->posted_at)->not->toBeNull();
});

it('renders the BKU from approved entries for the active school only', function () {
    $schoolId = makeFinanceSchool('Sekolah BKU A');
    $otherSchoolId = makeFinanceSchool('Sekolah BKU B');
    $user = makeFinanceUser($schoolId);
    $service = app(FinanceService::class);
    $account = $service->createAccount($schoolId, ['name' => 'Kas BKU A', 'type' => 'Tunai']);
    $otherAccount = $service->createAccount($otherSchoolId, ['name' => 'Kas BKU B', 'type' => 'Tunai']);
    $service->recordBosIncome($schoolId, [
        'source_funding' => 'BOS Sekolah A', 'amount' => 100000, 'received_date' => '2026-07-14', 'account_id' => $account->id,
    ]);
    $service->recordBosIncome($otherSchoolId, [
        'source_funding' => 'BOS Sekolah B', 'amount' => 900000, 'received_date' => '2026-07-14', 'account_id' => $otherAccount->id,
    ]);

    $this->actingAs($user)
        ->withSession(['active_school_id' => $schoolId])
        ->get('/bos/bku')
        ->assertOk()
        ->assertViewHas('ledger', fn ($ledger) => collect($ledger)->pluck('description')->contains('Penerimaan BOS Sekolah A'))
        ->assertViewHas('ledger', fn ($ledger) => ! collect($ledger)->pluck('description')->contains('Penerimaan BOS Sekolah B'));
});

it('rejects an approval request whose approvable belongs to another school', function () {
    $schoolId = makeFinanceSchool('Sekolah Approval Scope A');
    $otherSchoolId = makeFinanceSchool('Sekolah Approval Scope B');
    $kepsek = makeFinanceUser($schoolId, 'kepsek');
    $academicYearId = makeFinanceAcademicYear($otherSchoolId);
    $expenseId = DB::table('expenses')->insertGetId([
        'school_id' => $otherSchoolId,
        'academic_year_id' => $academicYearId,
        'expense_name' => 'Belanja Sekolah B',
        'amount' => 100000,
        'transaction_date' => '2026-07-15',
        'source_funding' => 'BOS',
        'payment_method' => 'Tunai',
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $approvalId = DB::table('approval_requests')->insertGetId([
        'school_id' => $schoolId,
        'requested_by' => $kepsek->id,
        'approvable_type' => Expense::class,
        'approvable_id' => $expenseId,
        'type' => 'expense',
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($kepsek)
        ->withSession(['active_school_id' => $schoolId])
        ->post("/approvals/{$approvalId}/approve")
        ->assertForbidden();

    expect(DB::table('expenses')->where('id', $expenseId)->value('status'))->toBe('pending');
});
