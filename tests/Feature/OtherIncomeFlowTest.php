<?php

use App\Models\ApprovalRequest;
use App\Models\FinanceIncome;
use App\Models\FundAllocation;
use App\Models\IncomeType;
use App\Models\SchoolAccount;
use App\Models\User;
use App\Models\VirtualWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function otherIncomeFixture($test): array
{
    $school = DB::table('schools')->insertGetId(['name' => 'Other Income School', 'level' => 'sma', 'ownership' => 'swasta', 'is_active' => true, 'status' => 'active']);
    $user = User::factory()->create(['role' => 'bendahara']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $user->id, 'role' => 'bendahara', 'is_active' => true, 'membership_status' => 'active']);
    $test->actingAs($user)->withSession(['active_school_id' => $school]);
    $cash = SchoolAccount::create(['school_id' => $school, 'name' => 'Kas', 'type' => 'Tunai', 'is_active' => true, 'current_balance' => 0]);
    $bank = SchoolAccount::create(['school_id' => $school, 'name' => 'Bank Komite', 'type' => 'Bank', 'source_funding' => 'komite', 'is_active' => true, 'current_balance' => 0]);
    $type = IncomeType::create(['school_id' => $school, 'name' => 'Pendapatan Kantin', 'category' => 'Lainnya', 'code' => 'OTHER']);
    $wallet = VirtualWallet::create(['school_id' => $school, 'name' => 'Operasional', 'status' => 'active', 'nominal' => 0]);
    FundAllocation::create(['school_id' => $school, 'income_type_id' => $type->id, 'virtual_wallet_id' => $wallet->id, 'name' => 'Operasional', 'method' => 'persentase', 'amount' => 25, 'status' => 'active']);

    return [$school, $user, $cash, $bank, $type, $wallet];
}

function otherIncomePayload(): array
{
    return ['category' => 'lainnya', 'source_funding' => 'Pendapatan Kantin', 'amount' => '100000.20', 'received_date' => '2026-10-12', 'payment_method' => 'Tunai'];
}

it('posts other cash income with matching allocations and private proof', function () {
    Storage::fake('local');
    [$school, $user, $cash, $bank, $type, $wallet] = otherIncomeFixture($this);
    $this->get('/spp/transaksi?category=lainnya')->assertOk()->assertSee('Pendapatan Lainnya');
    $this->post(route('spp.transaksi.pemasukan'), [...otherIncomePayload(), 'proof' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success', 'Transaksi berhasil disimpan');
    $income = FinanceIncome::first();
    expect($income->account_id)->toBe($cash->id)->and($income->source_funding)->toBe('Pendapatan Kantin')->and($cash->fresh()->current_balance)->toBe('100000.20')->and($wallet->fresh()->nominal)->toBe('25000.05')->and($income->proof_path)->toStartWith('finance/other-incomes/'.$school.'/');
    $this->get('/finance/other-incomes/'.$income->id.'/proof')->assertOk();
});

it('requires a committee bank for transfers and rejects missing fields or reserved categories', function () {
    [$school, $user, $cash, $bank] = otherIncomeFixture($this);
    $unmarked = SchoolAccount::create(['school_id' => $school, 'name' => 'Ordinary Bank', 'type' => 'Bank', 'is_active' => true, 'current_balance' => 0]);
    $this->post(route('spp.transaksi.pemasukan'), ['category' => 'lainnya'])->assertSessionHasErrors(['amount', 'received_date', 'source_funding', 'payment_method']);
    foreach ([null, $cash->id, $unmarked->id] as $id) {
        $this->post(route('spp.transaksi.pemasukan'), [...otherIncomePayload(), 'payment_method' => 'Transfer', 'account_id' => $id])->assertSessionHasErrors('account_id');
    }
    foreach (['Hibah', 'BOS Reguler', 'Komite'] as $source) {
        $this->post(route('spp.transaksi.pemasukan'), [...otherIncomePayload(), 'source_funding' => $source])->assertSessionHasErrors('source_funding');
    }
    expect(FinanceIncome::count())->toBe(0);
    $this->post(route('spp.transaksi.pemasukan'), [...otherIncomePayload(), 'payment_method' => 'Transfer', 'account_id' => $bank->id])->assertSessionHasNoErrors();
    expect($bank->fresh()->current_balance)->toBe('100000.20')->and($cash->fresh()->current_balance)->toBe('0.00');
});

it('keeps revisions pending and applies account and wallet corrections only after head approval', function () {
    [$school, $user, $cash, $bank, $type, $wallet] = otherIncomeFixture($this);
    $this->post(route('spp.transaksi.pemasukan'), otherIncomePayload());
    $income = FinanceIncome::first();
    $this->post('/finance/other-incomes/'.$income->id.'/revisions', [...otherIncomePayload(), 'amount' => '200000.40', 'payment_method' => 'Transfer', 'account_id' => $bank->id, 'reason' => 'Koreksi rekening dan nominal'])->assertSessionHasNoErrors();
    $approval = ApprovalRequest::where('type', 'other_income_revision')->firstOrFail();
    $this->post(route('approvals.approve', $approval))->assertForbidden();
    expect($cash->fresh()->current_balance)->toBe('100000.20')->and($wallet->fresh()->nominal)->toBe('25000.05');
    $head = User::factory()->create(['role' => 'kepsek']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $head->id, 'role' => 'kepsek', 'is_active' => true, 'membership_status' => 'active']);
    $this->actingAs($head)->post(route('approvals.approve', $approval))->assertSessionHasNoErrors();
    expect($cash->fresh()->current_balance)->toBe('0.00')->and($bank->fresh()->current_balance)->toBe('200000.40')->and($wallet->fresh()->nominal)->toBe('50000.10');
    $revision = $approval->fresh()->approvable;
    expect($revision->requested_by)->toBe($user->id)->and($revision->reviewed_by)->toBe($head->id)->and($revision->applied_at)->not->toBeNull();
    $this->post(route('approvals.approve', $approval))->assertStatus(422);
});

it('does not duplicate other income or allocate money to an unrelated income type', function () {
    Storage::fake('local');
    [$school, $user, $cash, $bank, $type, $wallet] = otherIncomeFixture($this);
    $grantType = IncomeType::create(['school_id' => $school, 'name' => 'Hibah', 'category' => 'Hibah', 'code' => 'GRANT']);
    $grantWallet = VirtualWallet::create(['school_id' => $school, 'name' => 'Hibah Wallet', 'status' => 'active', 'nominal' => 0]);
    FundAllocation::create(['school_id' => $school, 'income_type_id' => $grantType->id, 'virtual_wallet_id' => $grantWallet->id, 'name' => 'Hibah', 'method' => 'persentase', 'amount' => 50, 'status' => 'active']);
    $payload = [...otherIncomePayload(), 'other_submission_key' => (string) Illuminate\Support\Str::uuid(), 'proof' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf')];
    $this->post(route('spp.transaksi.pemasukan'), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('spp.transaksi.pemasukan'), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('spp.transaksi.pemasukan'), [...$payload, 'amount' => 200000])->assertSessionHasErrors('other_submission_key');
    $this->post(route('spp.transaksi.pemasukan'), [...otherIncomePayload(), 'income_type_id' => $grantType->id])->assertSessionHasErrors('income_type_id');
    expect(FinanceIncome::count())->toBe(1)->and($cash->fresh()->current_balance)->toBe('100000.20')->and($wallet->fresh()->nominal)->toBe('25000.05')->and($grantWallet->fresh()->nominal)->toBe('0.00')
        ->and(Storage::disk('local')->allFiles('finance/other-incomes/'.$school))->toHaveCount(1);
});

it('records rejection and preserves old and proposed documents within the school', function () {
    Storage::fake('local');
    [$school, $user, $cash, $bank, $type, $wallet] = otherIncomeFixture($this);
    $this->post(route('spp.transaksi.pemasukan'), [...otherIncomePayload(), 'proof' => UploadedFile::fake()->create('old.pdf', 10, 'application/pdf')]);
    $income = FinanceIncome::first();
    $payload = [...otherIncomePayload(), 'source_funding' => 'Kantin Alumni', 'reason' => 'Koreksi sumber', 'proof' => UploadedFile::fake()->create('new.pdf', 12, 'application/pdf')];
    $this->post('/finance/other-incomes/'.$income->id.'/revisions', $payload)->assertSessionHasNoErrors();
    $revision = $income->otherRevisions()->first();
    $this->get(route('finance.other-income.revision-proof', $revision))->assertOk();
    $this->get(route('finance.other-income.revision-proof', ['revision' => $revision, 'version' => 'old']))->assertOk();
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $user->id, 'role' => 'kepsek', 'is_active' => true, 'membership_status' => 'pending']);
    $approval = ApprovalRequest::first();
    $this->post(route('approvals.approve', $approval))->assertForbidden();
    $head = User::factory()->create(['role' => 'kepsek']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $head->id, 'role' => 'kepsek', 'is_active' => true, 'membership_status' => 'active']);
    $this->actingAs($head)->post(route('approvals.reject', $approval))->assertSessionHasNoErrors();
    expect($revision->fresh()->status)->toBe('rejected')->and($revision->fresh()->reviewed_by)->toBe($head->id)->and($income->fresh()->source_funding)->toBe('Pendapatan Kantin')->and($cash->fresh()->current_balance)->toBe('100000.20');
    $otherSchool = DB::table('schools')->insertGetId(['name' => 'Other School', 'level' => 'sma', 'ownership' => 'swasta', 'is_active' => true, 'status' => 'active']);
    DB::table('school_user_roles')->insert(['school_id' => $otherSchool, 'user_id' => $head->id, 'role' => 'kepsek', 'is_active' => true, 'membership_status' => 'active']);
    $this->withSession(['active_school_id' => $otherSchool])->get(route('finance.other-income.revision-proof', $revision))->assertNotFound();
    $this->get('/finance/other-incomes/'.$income->id.'/proof')->assertNotFound();
    $this->get('/finance/other-incomes/'.$income->id)->assertNotFound();
});

it('rolls back corrections if wallet funds are spent and rejects stale or overlapping requests', function () {
    [$school, $user, $cash, $bank, $type, $wallet] = otherIncomeFixture($this);
    $this->post(route('spp.transaksi.pemasukan'), otherIncomePayload());
    $income = FinanceIncome::first();
    $this->post('/finance/other-incomes/'.$income->id.'/revisions', [...otherIncomePayload(), 'reason' => 'Tanpa perubahan'])->assertSessionHasErrors('reason');
    $payload = [...otherIncomePayload(), 'amount' => 1000, 'reason' => 'Koreksi nominal'];
    $this->post('/finance/other-incomes/'.$income->id.'/revisions', $payload)->assertSessionHasNoErrors();
    $this->post('/finance/other-incomes/'.$income->id.'/revisions', $payload)->assertSessionHasErrors('reason');
    $wallet->refresh()->update(['nominal' => 0]);
    $head = User::factory()->create(['role' => 'kepsek']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $head->id, 'role' => 'kepsek', 'is_active' => true, 'membership_status' => 'active']);
    $approval = ApprovalRequest::first();
    $this->actingAs($head)->post(route('approvals.approve', $approval))->assertSessionHasErrors('revision');
    expect($approval->fresh()->status)->toBe('pending')->and($cash->fresh()->current_balance)->toBe('100000.20')->and($income->fresh()->amount)->toBe('100000.20');
    $wallet->refresh()->update(['nominal' => 25000.05]);
    $income->update(['source_funding' => 'Sumber sudah berubah']);
    $this->post(route('approvals.approve', $approval))->assertSessionHasErrors('revision');
    expect($approval->fresh()->status)->toBe('pending');
});
