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

function grantFixture($test): array
{
    $school = DB::table('schools')->insertGetId(['name' => 'Grant School', 'level' => 'sma', 'ownership' => 'swasta', 'is_active' => true, 'status' => 'active']);
    $user = User::factory()->create(['role' => 'bendahara']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $user->id, 'role' => 'bendahara', 'is_active' => true, 'membership_status' => 'active']);
    $test->actingAs($user)->withSession(['active_school_id' => $school]);
    $cash = SchoolAccount::create(['school_id' => $school, 'name' => 'Kas Sekolah', 'type' => 'Tunai', 'is_active' => true, 'current_balance' => 0]);
    $bank = SchoolAccount::create(['school_id' => $school, 'name' => 'Bank Sekolah', 'type' => 'Bank', 'is_active' => true, 'current_balance' => 0]);
    $type = IncomeType::create(['school_id' => $school, 'name' => 'Hibah Donatur', 'category' => 'Hibah', 'code' => 'HIBAH']);
    $wallet = VirtualWallet::create(['school_id' => $school, 'name' => 'Beasiswa', 'status' => 'active', 'nominal' => 0]);
    FundAllocation::create(['school_id' => $school, 'income_type_id' => $type->id, 'virtual_wallet_id' => $wallet->id, 'name' => 'Beasiswa', 'method' => 'persentase', 'amount' => 20, 'status' => 'active']);

    return [$school, $user, $cash, $bank, $type, $wallet];
}

function grantPayload(): array
{
    return ['category' => 'hibah', 'donor_name' => 'Donatur Alumni', 'amount' => '100000.25', 'received_date' => '2026-10-12', 'payment_method' => 'Tunai'];
}

it('renders a dedicated grant form and posts cash with only matching wallet allocations', function () {
    Storage::fake('local');
    [$school, $user, $cash, $bank, $type, $wallet] = grantFixture($this);
    $otherType = IncomeType::create(['school_id' => $school, 'name' => 'Komite', 'category' => 'Komite', 'code' => 'KOMITE']);
    $otherWallet = VirtualWallet::create(['school_id' => $school, 'name' => 'Komite Wallet', 'status' => 'active', 'nominal' => 0]);
    FundAllocation::create(['school_id' => $school, 'income_type_id' => $otherType->id, 'virtual_wallet_id' => $otherWallet->id, 'name' => 'Komite', 'method' => 'persentase', 'amount' => 50, 'status' => 'active']);
    $this->get('/spp/transaksi?category=hibah')->assertOk()->assertSee('Pemasukan Hibah')->assertSee('Nama Donatur');
    $this->post(route('spp.transaksi.pemasukan'), [...grantPayload(), 'proof' => UploadedFile::fake()->create('hibah.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors()->assertSessionHas('success', 'Transaksi berhasil disimpan');
    $income = FinanceIncome::first();
    expect($income->account_id)->toBe($cash->id)->and($cash->fresh()->current_balance)->toBe('100000.25')->and($bank->fresh()->current_balance)->toBe('0.00')
        ->and($wallet->fresh()->nominal)->toBe('20000.05')->and($otherWallet->fresh()->nominal)->toBe('0.00')->and($income->proof_path)->toStartWith('finance/grants/'.$school.'/');
    $this->get('/finance/grant-incomes/'.$income->id.'/proof')->assertOk();
});

it('requires a valid bank destination and rejects blank fields without crediting balances', function () {
    [$school, $user, $cash, $bank] = grantFixture($this);
    $this->post(route('spp.transaksi.pemasukan'), ['category' => 'hibah'])->assertSessionHasErrors(['donor_name', 'amount', 'received_date', 'payment_method']);
    $this->post(route('spp.transaksi.pemasukan'), [...grantPayload(), 'payment_method' => 'Transfer'])->assertSessionHasErrors('account_id');
    $this->post(route('spp.transaksi.pemasukan'), [...grantPayload(), 'payment_method' => 'Transfer', 'account_id' => $cash->id])->assertSessionHasErrors('account_id');
    expect(FinanceIncome::count())->toBe(0);
});

it('applies grant revisions only after head approval and adjusts account and wallet deltas', function () {
    [$school, $user, $cash, $bank, $type, $wallet] = grantFixture($this);
    $this->post(route('spp.transaksi.pemasukan'), grantPayload());
    $income = FinanceIncome::first();
    $this->post('/finance/grant-incomes/'.$income->id.'/revisions', [...grantPayload(), 'payment_method' => 'Transfer', 'account_id' => $bank->id, 'amount' => '200000.50', 'reason' => 'Koreksi bank dan nilai'])->assertSessionHasNoErrors();
    $approval = ApprovalRequest::where('type', 'grant_income_revision')->firstOrFail();
    $this->post(route('approvals.approve', $approval))->assertForbidden();
    expect($cash->fresh()->current_balance)->toBe('100000.25')->and($bank->fresh()->current_balance)->toBe('0.00')->and($wallet->fresh()->nominal)->toBe('20000.05');
    $head = User::factory()->create(['role' => 'kepsek']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $head->id, 'role' => 'kepsek', 'is_active' => true, 'membership_status' => 'active']);
    $this->actingAs($head)->post(route('approvals.approve', $approval))->assertSessionHasNoErrors();
    expect($cash->fresh()->current_balance)->toBe('0.00')->and($bank->fresh()->current_balance)->toBe('200000.50')->and($wallet->fresh()->nominal)->toBe('40000.10')->and($income->fresh()->amount)->toBe('200000.50');
    $revision = $approval->fresh()->approvable;
    expect($revision->requested_by)->toBe($user->id)->and($revision->reviewed_by)->toBe($head->id)->and($revision->applied_at)->not->toBeNull();
    $this->post(route('approvals.approve', $approval))->assertStatus(422);
    expect($bank->fresh()->current_balance)->toBe('200000.50');
});

it('does not duplicate receipts on retries and rolls back invalid allocations', function () {
    Storage::fake('local');
    [$school, $user, $cash, $bank, $type, $wallet] = grantFixture($this);
    $payload = [...grantPayload(), 'grant_submission_key' => (string) Illuminate\Support\Str::uuid(), 'proof' => UploadedFile::fake()->create('same.pdf', 10, 'application/pdf')];
    $this->post(route('spp.transaksi.pemasukan'), $payload)->assertSessionHasNoErrors();
    $this->post(route('spp.transaksi.pemasukan'), $payload)->assertSessionHasNoErrors();
    $this->post(route('spp.transaksi.pemasukan'), [...$payload, 'amount' => 200000])->assertSessionHasErrors('grant_submission_key');
    expect(FinanceIncome::count())->toBe(1)->and($cash->fresh()->current_balance)->toBe('100000.25')->and($wallet->fresh()->nominal)->toBe('20000.05')
        ->and(Storage::disk('local')->allFiles('finance/grants/'.$school))->toHaveCount(1);
    FundAllocation::create(['school_id' => $school, 'income_type_id' => $type->id, 'virtual_wallet_id' => $wallet->id, 'name' => 'Too Much', 'method' => 'persentase', 'amount' => 90, 'status' => 'active']);
    $this->post(route('spp.transaksi.pemasukan'), grantPayload())->assertSessionHasErrors('amount');
    expect(FinanceIncome::count())->toBe(1)->and($cash->fresh()->current_balance)->toBe('100000.25');
    FundAllocation::where('income_type_id', $type->id)->update(['amount' => '-0.50']);
    $this->post(route('spp.transaksi.pemasukan'), grantPayload())->assertSessionHasErrors('amount');
    expect(FinanceIncome::count())->toBe(1);
});

it('rejects wrong school and category destinations and hides private documents from other schools', function () {
    Storage::fake('local');
    [$school, $user, $cash, $bank, $type, $wallet] = grantFixture($this);
    $otherSchool = DB::table('schools')->insertGetId(['name' => 'Other', 'level' => 'sma', 'ownership' => 'swasta', 'is_active' => true, 'status' => 'active']);
    $otherBank = SchoolAccount::create(['school_id' => $otherSchool, 'name' => 'Other Bank', 'type' => 'Bank', 'is_active' => true, 'current_balance' => 0]);
    $bosBank = SchoolAccount::create(['school_id' => $school, 'name' => 'BOS Bank', 'type' => 'Bank', 'source_funding' => 'bos', 'is_active' => true, 'current_balance' => 0]);
    foreach ([$otherBank, $bosBank] as $destination) {
        $this->post(route('spp.transaksi.pemasukan'), [...grantPayload(), 'payment_method' => 'Transfer', 'account_id' => $destination->id])->assertSessionHasErrors('account_id');
    }
    $foreignType = IncomeType::create(['school_id' => $otherSchool, 'name' => 'Foreign Hibah', 'category' => 'Hibah', 'code' => 'F']);
    $this->post(route('spp.transaksi.pemasukan'), [...grantPayload(), 'income_type_id' => $foreignType->id])->assertSessionHasErrors('income_type_id');
    $this->post(route('spp.transaksi.pemasukan'), [...grantPayload(), 'proof' => UploadedFile::fake()->create('hibah.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
    $income = FinanceIncome::first();
    $this->post('/finance/grant-incomes/'.$income->id.'/revisions', [...grantPayload(), 'donor_name' => 'Nama dikoreksi', 'reason' => 'Koreksi nama'])->assertSessionHasNoErrors();
    $revision = $income->grantRevisions()->first();
    DB::table('school_user_roles')->insert(['school_id' => $otherSchool, 'user_id' => $user->id, 'role' => 'bendahara', 'is_active' => true, 'membership_status' => 'active']);
    $this->withSession(['active_school_id' => $otherSchool])->get('/finance/grant-incomes/'.$income->id)->assertNotFound();
    $this->get('/finance/grant-incomes/'.$income->id.'/proof')->assertNotFound();
    $this->get(route('finance.grant-income.revision-proof', $revision))->assertNotFound();
    $this->post('/finance/grant-incomes/'.$income->id.'/revisions', [...grantPayload(), 'reason' => 'Foreign'])->assertNotFound();
    expect($otherBank->fresh()->current_balance)->toBe('0.00')->and($bosBank->fresh()->current_balance)->toBe('0.00');
});

it('keeps corrections pending if allocated funds have been spent and records rejection without changing balances', function () {
    [$school, $user, $cash, $bank, $type, $wallet] = grantFixture($this);
    $this->post(route('spp.transaksi.pemasukan'), grantPayload());
    $income = FinanceIncome::first();
    $this->post('/finance/grant-incomes/'.$income->id.'/revisions', [...grantPayload(), 'amount' => 1000, 'reason' => 'Koreksi nominal']);
    $approval = ApprovalRequest::firstOrFail();
    $wallet->refresh()->update(['nominal' => 0]);
    $head = User::factory()->create(['role' => 'kepsek']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $head->id, 'role' => 'kepsek', 'is_active' => true, 'membership_status' => 'active']);
    $response = $this->actingAs($head)->post(route('approvals.approve', $approval));
    $response->assertRedirect()->assertSessionHasErrors('revision');
    expect($cash->fresh()->current_balance)->toBe('100000.25')->and($income->fresh()->amount)->toBe('100000.25')->and($approval->fresh()->status)->toBe('pending');
    $this->post(route('approvals.reject', $approval))->assertSessionHasNoErrors();
    expect($income->grantRevisions()->first()->status)->toBe('rejected')->and($approval->fresh()->reviewed_by)->toBe($head->id)->and($wallet->fresh()->nominal)->toBe('0.00');
});

it('calculates large allowed percentage allocations without integer overflow', function () {
    [$school, $user, $cash, $bank, $type, $wallet] = grantFixture($this);
    FundAllocation::where('income_type_id', $type->id)->update(['amount' => 99.99]);
    $this->post(route('spp.transaksi.pemasukan'), [...grantPayload(), 'amount' => '9999999999999.99'])->assertRedirect()->assertSessionHasNoErrors();
    expect(FinanceIncome::first()->grant_allocations[0]['cents'])->toBe(999899999999999);
});

it('distributes rounding cents between valid percentage allocations without overcrediting', function () {
    [$school, $user, $cash, $bank, $type, $wallet] = grantFixture($this);
    FundAllocation::where('income_type_id', $type->id)->update(['amount' => 50]);
    $second = VirtualWallet::create(['school_id' => $school, 'name' => 'Second', 'status' => 'active', 'nominal' => 0]);
    FundAllocation::create(['school_id' => $school, 'income_type_id' => $type->id, 'virtual_wallet_id' => $second->id, 'name' => 'Other Half', 'method' => 'persentase', 'amount' => 50, 'status' => 'active']);
    $this->post(route('spp.transaksi.pemasukan'), [...grantPayload(), 'amount' => '0.01'])->assertRedirect()->assertSessionHasNoErrors();
    expect($wallet->fresh()->nominal)->toBe('0.01')->and($second->fresh()->nominal)->toBe('0.00')->and($cash->fresh()->current_balance)->toBe('0.01');
});

it('prevents grants from bypassing validation through the other income category', function () {
    grantFixture($this);
    $this->post(route('spp.transaksi.pemasukan'), [...grantPayload(), 'category' => 'lainnya', 'source_funding' => 'Hibah'])->assertSessionHasErrors('source_funding');
    expect(FinanceIncome::count())->toBe(0);
});

it('preserves old and proposed proofs and rejects stale or overlapping revisions', function () {
    Storage::fake('local');
    [$school, $user, $cash] = grantFixture($this);
    $this->post(route('spp.transaksi.pemasukan'), [...grantPayload(), 'proof' => UploadedFile::fake()->create('original.pdf', 10, 'application/pdf')]);
    $income = FinanceIncome::first();
    $this->post('/finance/grant-incomes/'.$income->id.'/revisions', [...grantPayload(), 'reason' => 'Tanpa perubahan'])->assertSessionHasErrors('reason');
    $payload = [...grantPayload(), 'donor_name' => 'Alumni Baru', 'reason' => 'Koreksi nama', 'proof' => UploadedFile::fake()->create('proposed.pdf', 11, 'application/pdf')];
    $this->post('/finance/grant-incomes/'.$income->id.'/revisions', $payload)->assertSessionHasNoErrors();
    $this->post('/finance/grant-incomes/'.$income->id.'/revisions', $payload)->assertSessionHasErrors('reason');
    $revision = $income->grantRevisions()->first();
    $this->get(route('finance.grant-income.revision-proof', $revision))->assertOk();
    $this->get(route('finance.grant-income.revision-proof', ['revision' => $revision, 'version' => 'old']))->assertOk();
    expect(Storage::disk('local')->allFiles('finance/grants/'.$school))->toHaveCount(2);
    $income->update(['donor_name' => 'Changed concurrently']);
    $head = User::factory()->create(['role' => 'kepsek']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $head->id, 'role' => 'kepsek', 'is_active' => true, 'membership_status' => 'active']);
    $approval = ApprovalRequest::first();
    $this->actingAs($head)->post(route('approvals.approve', $approval))->assertSessionHasErrors('revision');
    expect($approval->fresh()->status)->toBe('pending')->and($cash->fresh()->current_balance)->toBe('100000.25');
});

it('reallocates a changed grant type without using unrelated allocation rules', function () {
    [$school, $user, $cash, $bank, $type, $wallet] = grantFixture($this);
    $this->post(route('spp.transaksi.pemasukan'), grantPayload());
    $income = FinanceIncome::first();
    $secondType = IncomeType::create(['school_id' => $school, 'name' => 'Hibah Yayasan', 'category' => 'Hibah', 'code' => 'HY']);
    $secondWallet = VirtualWallet::create(['school_id' => $school, 'name' => 'Sarana', 'status' => 'active', 'nominal' => 0]);
    FundAllocation::create(['school_id' => $school, 'income_type_id' => $secondType->id, 'virtual_wallet_id' => $secondWallet->id, 'name' => 'Sarana', 'method' => 'nominal', 'amount' => 5000, 'status' => 'active']);
    $this->post(route('spp.transaksi.pemasukan'), grantPayload())->assertSessionHasErrors('income_type_id');
    $this->post('/finance/grant-incomes/'.$income->id.'/revisions', [...grantPayload(), 'income_type_id' => $secondType->id, 'reason' => 'Jenis Hibah dikoreksi'])->assertSessionHasNoErrors();
    $head = User::factory()->create(['role' => 'kepsek']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $head->id, 'role' => 'kepsek', 'is_active' => true, 'membership_status' => 'active']);
    $this->actingAs($head)->post(route('approvals.approve', ApprovalRequest::first()))->assertSessionHasNoErrors();
    expect($wallet->fresh()->nominal)->toBe('0.00')->and($secondWallet->fresh()->nominal)->toBe('5000.00')->and($cash->fresh()->current_balance)->toBe('100000.25');
});
