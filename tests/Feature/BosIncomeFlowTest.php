<?php

use App\Models\ApprovalRequest;
use App\Models\FinanceIncome;
use App\Models\SchoolAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function bosIncomeFixture($test): array
{
    $school = DB::table('schools')->insertGetId(['name' => 'BOS Income School', 'level' => 'sma', 'ownership' => 'swasta', 'is_active' => true, 'status' => 'active']);
    $user = User::factory()->create(['role' => 'bendahara']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $user->id, 'role' => 'bendahara', 'is_active' => true, 'membership_status' => 'active']);
    $test->actingAs($user)->withSession(['active_school_id' => $school]);
    $account = SchoolAccount::create(['school_id' => $school, 'name' => 'Bank BOS', 'type' => 'Bank', 'source_funding' => 'bos', 'current_balance' => 0, 'is_active' => true]);
    App\Models\IncomeType::create(['school_id' => $school, 'name' => 'BOS Sekolah Khusus', 'category' => 'BOS', 'code' => 'BOS-CUSTOM']);

    return [$school, $user, $account];
}

function bosIncomePayload(): array
{
    return ['category' => 'bos', 'source_funding' => 'BOS Reguler', 'bos_year' => 2026, 'amount' => 500000, 'received_date' => '2026-10-12'];
}

it('renders bos source and year dropdowns from school data', function () {
    [$school] = bosIncomeFixture($this);
    \App\Models\BudgetYear::create(['school_id' => $school, 'name' => 'TA 2028', 'start_date' => '2028-01-01', 'end_date' => '2028-12-31', 'status' => 'active']);
    $years = app(\App\Services\BosIncomeService::class)->years($school);
    expect($years)->toContain(2028);
    $this->get('/spp/transaksi?category=bos')->assertOk()->assertSee('Pemasukan BOS')->assertSee('BOS Sekolah Khusus')->assertSee('name="bos_year"', false)->assertSee('SP2D');
});

it('records approved bos receipts into the designated account and private sp2d storage', function () {
    Storage::fake('local');
    [$school, $user, $account] = bosIncomeFixture($this);
    $this->post(route('spp.transaksi.pemasukan'), [...bosIncomePayload(), 'proof' => UploadedFile::fake()->create('sp2d.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors()->assertSessionHas('success', 'Transaksi berhasil disimpan');
    $income = FinanceIncome::first();
    expect($income->account_id)->toBe($account->id)->and($income->status)->toBe('approved')->and($account->fresh()->current_balance)->toBe('500000.00')->and($income->proof_path)->toStartWith('finance/bos/'.$school.'/');
    $this->get('/finance/bos-incomes/'.$income->id.'/proof')->assertOk();
    expect(app(App\Services\FinanceLedgerService::class)->entries($school)->sum('amount'))->toEqual(500000);
});

it('rejects missing fields unknown sources and unmarked bos accounts without posting money', function () {
    [$school, $user, $account] = bosIncomeFixture($this);
    $this->post(route('spp.transaksi.pemasukan'), ['category' => 'bos', 'bos_year' => 2026])->assertSessionHasErrors(['amount', 'source_funding', 'received_date']);
    $this->post(route('spp.transaksi.pemasukan'), [...bosIncomePayload(), 'source_funding' => 'Forged Source'])->assertSessionHasErrors('source_funding');
    $account->update(['source_funding' => 'komite']);
    $this->post(route('spp.transaksi.pemasukan'), bosIncomePayload())->assertSessionHasErrors('account_id');
    expect(FinanceIncome::count())->toBe(0)->and($account->fresh()->current_balance)->toBe('0.00');
});

it('keeps proposed revisions pending until the head approves the balance delta once', function () {
    [$school, $treasurer, $account] = bosIncomeFixture($this);
    $this->post(route('spp.transaksi.pemasukan'), bosIncomePayload())->assertSessionHasNoErrors();
    $income = FinanceIncome::first();
    $this->post('/finance/bos-incomes/'.$income->id.'/revisions', [...bosIncomePayload(), 'amount' => 650000, 'reason' => 'Koreksi nilai SP2D'])->assertSessionHasNoErrors();
    $approval = ApprovalRequest::where('type', 'bos_income_revision')->firstOrFail();
    expect($income->fresh()->amount)->toBe('500000.00')->and($account->fresh()->current_balance)->toBe('500000.00');
    $this->post(route('approvals.approve', $approval))->assertForbidden();
    $head = User::factory()->create(['role' => 'kepsek']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $head->id, 'role' => 'kepsek', 'is_active' => true, 'membership_status' => 'active']);
    $this->actingAs($head)->post(route('approvals.approve', $approval))->assertSessionHasNoErrors();
    expect($income->fresh()->amount)->toBe('650000.00')->and($account->fresh()->current_balance)->toBe('650000.00');
    $revision = DB::table('bos_income_revisions')->first();
    expect((int) $revision->requested_by)->toBe($treasurer->id)->and((int) $revision->reviewed_by)->toBe($head->id)->and($revision->applied_at)->not->toBeNull();
    $this->post(route('approvals.approve', $approval))->assertStatus(422);
    expect($account->fresh()->current_balance)->toBe('650000.00');
});

it('does not double credit a bos form retry and cleans duplicate uploaded copies', function () {
    Storage::fake('local');
    [$school, $user, $account] = bosIncomeFixture($this);
    $payload = [...bosIncomePayload(), 'bos_submission_key' => (string) Illuminate\Support\Str::uuid(), 'proof' => UploadedFile::fake()->create('same-sp2d.pdf', 10, 'application/pdf')];
    $this->post(route('spp.transaksi.pemasukan'), $payload)->assertSessionHasNoErrors();
    $this->post(route('spp.transaksi.pemasukan'), $payload)->assertSessionHasNoErrors();
    expect(FinanceIncome::count())->toBe(1)->and($account->fresh()->current_balance)->toBe('500000.00')
        ->and(Storage::disk('local')->allFiles('finance/bos/'.$school))->toHaveCount(1);
});

it('requires explicit destination selection when more than one bos account exists', function () {
    [$school, $user, $account] = bosIncomeFixture($this);
    $second = SchoolAccount::create(['school_id' => $school, 'name' => 'Second BOS', 'type' => 'Bank', 'source_funding' => 'bos', 'is_active' => true, 'current_balance' => 0]);
    $this->post(route('spp.transaksi.pemasukan'), bosIncomePayload())->assertSessionHasErrors('account_id');
    $this->post(route('spp.transaksi.pemasukan'), [...bosIncomePayload(), 'account_id' => $second->id])->assertSessionHasNoErrors();
    expect($account->fresh()->current_balance)->toBe('0.00')->and($second->fresh()->current_balance)->toBe('500000.00');
});

it('does not permit a pending school membership to approve a bos correction', function () {
    [$school, $user, $account] = bosIncomeFixture($this);
    $this->post(route('spp.transaksi.pemasukan'), bosIncomePayload());
    $income = FinanceIncome::first();
    $this->post('/finance/bos-incomes/'.$income->id.'/revisions', [...bosIncomePayload(), 'amount' => 600000, 'reason' => 'Revisi SP2D']);
    $head = User::factory()->create(['role' => 'kepsek']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $head->id, 'role' => 'kepsek', 'is_active' => true, 'membership_status' => 'pending']);
    $approval = ApprovalRequest::where('type', 'bos_income_revision')->first();
    $this->actingAs($head)->post(route('approvals.approve', $approval))->assertForbidden();
    expect($account->fresh()->current_balance)->toBe('500000.00')->and($approval->fresh()->status)->toBe('pending');
});

it('does not grant pending head authority to an active treasurer', function () {
    [$school, $user, $account] = bosIncomeFixture($this);
    $this->post(route('spp.transaksi.pemasukan'), bosIncomePayload());
    $income = FinanceIncome::first();
    $this->post('/finance/bos-incomes/'.$income->id.'/revisions', [...bosIncomePayload(), 'amount' => 600000, 'reason' => 'Revisi SP2D']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $user->id, 'role' => 'kepsek', 'is_active' => true, 'membership_status' => 'pending']);
    $approval = ApprovalRequest::where('type', 'bos_income_revision')->first();
    $this->post(route('approvals.approve', $approval))->assertForbidden();
    expect($account->fresh()->current_balance)->toBe('500000.00')->and($approval->fresh()->status)->toBe('pending');
});

it('retains both private document versions when a head rejects a revision', function () {
    Storage::fake('local');
    [$school, $user, $account] = bosIncomeFixture($this);
    $this->post(route('spp.transaksi.pemasukan'), [...bosIncomePayload(), 'proof' => UploadedFile::fake()->create('original.pdf', 10, 'application/pdf')]);
    $income = FinanceIncome::first();
    $this->post('/finance/bos-incomes/'.$income->id.'/revisions', [...bosIncomePayload(), 'amount' => 600000, 'reason' => 'Koreksi SP2D', 'proof' => UploadedFile::fake()->create('replacement.pdf', 12, 'application/pdf')])->assertSessionHasNoErrors();
    $revision = App\Models\BosIncomeRevision::first();
    expect($revision->new_data['proof_path'])->not->toBe($income->proof_path);
    $this->get(route('finance.bos-income.revision-proof', $revision))->assertOk();
    $this->get(route('finance.bos-income.revision-proof', ['revision' => $revision, 'version' => 'old']))->assertOk();
    $head = User::factory()->create(['role' => 'kepsek']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $head->id, 'role' => 'kepsek', 'is_active' => true, 'membership_status' => 'active']);
    $this->actingAs($head)->post(route('approvals.reject', ApprovalRequest::first()))->assertSessionHasNoErrors();
    expect($revision->fresh()->status)->toBe('rejected')->and($revision->fresh()->reviewed_by)->toBe($head->id)
        ->and($income->fresh()->proof_path)->toBe($income->proof_path)->and($account->fresh()->current_balance)->toBe('500000.00');
    expect(Storage::disk('local')->allFiles('finance/bos/'.$school))->toHaveCount(2);
    $otherSchool = DB::table('schools')->insertGetId(['name' => 'Other BOS School', 'level' => 'sma', 'ownership' => 'swasta', 'is_active' => true, 'status' => 'active']);
    DB::table('school_user_roles')->insert(['school_id' => $otherSchool, 'user_id' => $head->id, 'role' => 'kepsek', 'is_active' => true, 'membership_status' => 'active']);
    $this->withSession(['active_school_id' => $otherSchool])->get(route('finance.bos-income.revision-proof', $revision))->assertNotFound();
    $this->get('/finance/bos-incomes/'.$income->id)->assertNotFound();
});

it('rolls back approvals if the receipt changed or the account cannot cover a reduction', function () {
    [$school, $user, $account] = bosIncomeFixture($this);
    $this->post(route('spp.transaksi.pemasukan'), bosIncomePayload());
    $income = FinanceIncome::first();
    $this->post('/finance/bos-incomes/'.$income->id.'/revisions', [...bosIncomePayload(), 'amount' => 200000, 'reason' => 'Pengurangan SP2D']);
    $approval = ApprovalRequest::first();
    $head = User::factory()->create(['role' => 'kepsek']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $head->id, 'role' => 'kepsek', 'is_active' => true, 'membership_status' => 'active']);
    $account->update(['current_balance' => 100000]);
    $this->actingAs($head)->post(route('approvals.approve', $approval))->assertSessionHasErrors('revision');
    expect($approval->fresh()->status)->toBe('pending')->and($income->fresh()->amount)->toBe('500000.00')->and($account->fresh()->current_balance)->toBe('100000.00');
    $account->update(['current_balance' => 500000]);
    $income->update(['received_date' => '2026-10-13']);
    $this->post(route('approvals.approve', $approval))->assertSessionHasErrors('revision');
    expect($approval->fresh()->status)->toBe('pending')->and($account->fresh()->current_balance)->toBe('500000.00');
});

it('rejects unchanged and overlapping revisions while preserving fractional amounts', function () {
    [$school, $user, $account] = bosIncomeFixture($this);
    $this->post(route('spp.transaksi.pemasukan'), [...bosIncomePayload(), 'amount' => '500.000,25'])->assertSessionHasNoErrors();
    $income = FinanceIncome::first();
    expect($income->amount)->toBe('500000.25')->and($account->fresh()->current_balance)->toBe('500000.25');
    $payload = [...bosIncomePayload(), 'amount' => '500000.25', 'reason' => 'Periksa data'];
    $this->post('/finance/bos-incomes/'.$income->id.'/revisions', $payload)->assertSessionHasErrors('reason');
    expect(ApprovalRequest::count())->toBe(0);
    $payload['amount'] = '500000.50';
    $this->post('/finance/bos-incomes/'.$income->id.'/revisions', $payload)->assertSessionHasNoErrors();
    $this->post('/finance/bos-incomes/'.$income->id.'/revisions', $payload)->assertSessionHasErrors('reason');
    expect(ApprovalRequest::count())->toBe(1);
});
