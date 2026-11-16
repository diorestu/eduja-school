<?php

use App\Models\BookClosing;
use App\Models\FinanceIncome;
use App\Models\SchoolAccount;
use App\Models\User;
use App\Services\BosClosingService;
use App\Services\FinanceLedgerService;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function bosClosingFixture(): array
{
    Storage::fake('public');
    Storage::disk('public')->put('bos/proof.pdf', 'test proof');
    $school = DB::table('schools')->insertGetId(['name' => 'Sekolah Tutup BOS', 'level' => 'sma', 'ownership' => 'swasta', 'status' => 'active', 'is_active' => true]);
    $user = User::factory()->create(['role' => 'bendahara']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $user->id, 'role' => 'bendahara', 'is_active' => true, 'membership_status' => 'active']);
    $account = app(FinanceService::class)->createAccount($school, ['name' => 'Bank BOS', 'type' => 'Bank', 'source_funding' => 'bos', 'opening_balance' => 1000000]);

    return [$school, $user, $account];
}

function bosClosingIncome(int $school, SchoolAccount $account, array $data = []): FinanceIncome
{
    return app(FinanceService::class)->recordBosIncome($school, [...['source_funding' => 'BOS Reguler', 'bos_year' => 2025, 'amount' => 500, 'received_date' => '2025-07-10', 'account_id' => $account->id, 'proof_path' => 'bos/proof.pdf'], ...$data]);
}

it('scopes annual BOS checks by declared fiscal year source and school and allows an unspent balance', function () {
    [$school, $user, $account] = bosClosingFixture();
    bosClosingIncome($school, $account, ['received_date' => '2026-01-10']);
    FinanceIncome::create(['school_id' => $school, 'source_funding' => 'BOS Kinerja', 'bos_year' => 2025, 'amount' => 100, 'received_date' => '2025-01-10', 'status' => 'pending']);
    FinanceIncome::create(['school_id' => $school, 'source_funding' => 'BOS Reguler', 'bos_year' => 2026, 'amount' => 100, 'received_date' => '2025-01-10', 'status' => 'pending']);
    FinanceIncome::create(['school_id' => $school, 'source_funding' => 'komite', 'amount' => 100, 'received_date' => '2025-01-10', 'status' => 'pending']);
    $preview = app(BosClosingService::class)->preview($school, 'BOS Reguler', 2025);
    expect($preview['validation']['all_passed'])->toBeTrue()->and($preview['summary']['collected'])->toEqual(500)
        ->and($preview['summary']['spent'])->toEqual(0)->and($preview['entries'])->toHaveCount(1);
});

it('rechecks BOS confirmation and saves immutable proof notes and next opening without adding money', function () {
    [$school, $user, $account] = bosClosingFixture();
    $service = app(BosClosingService::class);
    $preview = $service->preview($school, 'BOS Reguler', 2025);
    bosClosingIncome($school, $account);
    expect(fn () => $service->close($school, 'BOS Reguler', 2025, $user->id, $preview['fingerprint'], 'Catatan'))->toThrow(ValidationException::class);
    $preview = $service->preview($school, 'BOS Reguler', 2025);
    $closing = $service->close($school, 'BOS Reguler', 2025, $user->id, $preview['fingerprint'], 'Sudah ditinjau');
    expect($closing->snapshot['book'])->toBe('bos')->and($closing->snapshot['next_opening']['date'])->toBe('2026-01-01')
        ->and($closing->audit_metadata['notes'])->toBe('Sudah ditinjau')->and($closing->audit_metadata['reference'])->not->toBeEmpty()
        ->and($account->fresh()->current_balance)->toBe('1000500.00');
    expect(fn () => bosClosingIncome($school, $account, ['received_date' => '2027-01-01']))->toThrow(ValidationException::class);
    bosClosingIncome($school, $account, ['bos_year' => 2026, 'amount' => 50, 'received_date' => '2025-01-01']);
    bosClosingIncome($school, $account, ['source_funding' => 'BOS Kinerja', 'amount' => 50]);
    expect($closing->fresh()->snapshot['summary']['collected'])->toEqual(500)
        ->and(app(FinanceLedgerService::class)->bku($school, ['from' => '2026-01-01'])['openingBalance'])->toEqual(1000600);
});

it('blocks ambiguous fiscal mappings unavailable proof and unposted BOS transactions', function () {
    [$school, $user, $account] = bosClosingFixture();
    bosClosingIncome($school, $account, ['bos_year' => null, 'proof_path' => 'missing.pdf']);
    $preview = app(BosClosingService::class)->preview($school, 'BOS Reguler', 2025);
    expect($preview['validation']['all_passed'])->toBeFalse()->and($preview['validation']['ambiguous_years'])->toBe(1)
        ->and($preview['validation']['missing_documents'])->toBe(1);
});

it('locks shared BOS physical transfers after annual closure but leaves committee transfers open', function () {
    [$school, $user, $account] = bosClosingFixture();
    $otherBos = app(FinanceService::class)->createAccount($school, ['name' => 'Kas BOS', 'type' => 'Tunai', 'source_funding' => 'bos', 'opening_balance' => 0]);
    $service = app(BosClosingService::class);
    $preview = $service->preview($school, 'BOS Reguler', 2025);
    $service->close($school, 'BOS Reguler', 2025, $user->id, $preview['fingerprint']);
    expect(fn () => App\Models\FinanceAccountTransfer::create(['school_id' => $school, 'from_account_id' => $account->id, 'to_account_id' => $otherBos->id, 'amount' => 50, 'transfer_date' => '2025-07-10']))->toThrow(ValidationException::class);
    $committee = app(FinanceService::class)->createAccount($school, ['name' => 'Kas Komite', 'type' => 'Tunai', 'opening_balance' => 100]);
    $committee2 = app(FinanceService::class)->createAccount($school, ['name' => 'Bank Komite', 'type' => 'Bank', 'opening_balance' => 0]);
    $transfer = App\Models\FinanceAccountTransfer::create(['school_id' => $school, 'from_account_id' => $committee->id, 'to_account_id' => $committee2->id, 'amount' => 50, 'transfer_date' => '2025-07-10']);
    expect($transfer->exists)->toBeTrue();
});

it('requires a checked confirmation and serves scoped immutable BOS reports and receipt history', function () {
    [$school, $user, $account] = bosClosingFixture();
    bosClosingIncome($school, $account);
    $this->actingAs($user)->withSession(['active_school_id' => $school]);
    $this->get(route('finance.closing', ['tab' => 'bos']))->assertOk()->assertSee('Pilih Tahun BOS')->assertSee('Belum diperiksa');
    $this->post(route('finance.closing.store'), ['type' => 'bos', 'bos_type' => 'BOS Reguler', 'period' => 2025])->assertSessionHasErrors(['fingerprint', 'confirmed']);
    $preview = $this->get(route('finance.closing', ['tab' => 'bos', 'bos_type' => 'BOS Reguler', 'period' => 2025, 'check' => 1]))->assertOk()->assertSee('Semua persyaratan terpenuhi')->viewData('preview');
    foreach (['financial', 'receipt'] as $report) {
        $this->get(route('finance.closing.bos.report', ['bos_type' => 'BOS Reguler', 'period' => 2025, 'report' => $report]))->assertOk()->assertSee('Draf sebelum penutupan');
    }
    $response = $this->post(route('finance.closing.store'), ['type' => 'bos', 'bos_type' => 'BOS Reguler', 'period' => 2025, 'fingerprint' => $preview['fingerprint'], 'confirmed' => 1, 'notes' => 'Pemeriksaan selesai'])->assertSessionHasNoErrors()->assertSessionHas('success');
    $this->get($response->headers->get('Location'))->assertOk()->assertSee('Berhasil ditutup')->assertSee('Pemeriksaan selesai')->assertSee('TB-BOS-2025-');
    $closing = BookClosing::where('school_id', $school)->firstOrFail();
    foreach (['financial', 'receipt'] as $report) {
        $this->get(route('finance.closing.bos.report', ['closing_id' => $closing->id, 'report' => $report]))->assertOk()->assertSee('Snapshot penutupan tersimpan')->assertSee('Pemeriksaan selesai');
    }
    session()->forget('active_school_id');
    [$otherSchool, $otherUser] = bosClosingFixture();
    $this->actingAs($otherUser)->withSession(['active_school_id' => $otherSchool])->get(route('finance.closing.bos.report', ['closing_id' => $closing->id, 'report' => 'receipt']))->assertNotFound();
});

it('checks tax settlement missing documents and revisions entering the selected BOS year', function () {
    [$school, $user, $account] = bosClosingFixture();
    $income = bosClosingIncome($school, $account, ['bos_year' => 2026, 'received_date' => '2026-01-10']);
    $revision = App\Models\BosIncomeRevision::create(['school_id' => $school, 'finance_income_id' => $income->id, 'old_data' => ['source_funding' => 'BOS Reguler', 'bos_year' => 2026, 'received_date' => '2026-01-10'], 'new_data' => ['source_funding' => 'BOS Reguler', 'bos_year' => 2025, 'received_date' => '2026-01-10'], 'requested_by' => $user->id, 'fingerprint' => 'test', 'reason' => 'Koreksi tahun', 'status' => 'pending']);
    App\Models\ApprovalRequest::create(['school_id' => $school, 'requested_by' => $user->id, 'type' => 'bos_income_revision', 'approvable_type' => $revision::class, 'approvable_id' => $revision->id, 'status' => 'pending']);
    $academic = App\Models\AcademicYear::create(['school_id' => $school, 'year' => '2025/2026', 'semester' => 'Ganjil', 'is_active' => true]);
    $expense = App\Models\Expense::create(['school_id' => $school, 'academic_year_id' => $academic->id, 'source_funding' => 'BOS Reguler', 'bos_year' => 2025, 'expense_name' => 'ATK', 'amount' => 100, 'transaction_date' => '2025-07-10', 'account_id' => $account->id, 'payment_method' => 'Transfer', 'status' => 'approved', 'tax_amount' => 5, 'is_tax_paid' => false]);
    app(App\Services\ExpensePostingService::class)->post($expense, $user->id);
    $preview = app(BosClosingService::class)->preview($school, 'BOS Reguler', 2025);
    expect($preview['validation']['pending_approvals'])->toBe(1)->and($preview['validation']['unpaid_taxes'])->toBe(1)
        ->and($preview['validation']['missing_documents'])->toBe(1)->and($preview['validation']['all_passed'])->toBeFalse();
});

it('keeps historical BOS wallet allocations locked through their source transaction', function () {
    [$school, $user, $account] = bosClosingFixture();
    $income = bosClosingIncome($school, $account);
    $wallet = App\Models\VirtualWallet::create(['school_id' => $school, 'name' => 'Dompet BOS', 'source' => 'bos', 'status' => 'active', 'nominal' => 0]);
    $service = app(BosClosingService::class);
    $preview = $service->preview($school, 'BOS Reguler', 2025);
    $service->close($school, 'BOS Reguler', 2025, $user->id, $preview['fingerprint']);
    expect(fn () => App\Models\FinanceWalletMovement::create(['school_id' => $school, 'virtual_wallet_id' => $wallet->id, 'amount' => 100, 'effective_date' => '2026-01-10', 'source_type' => $income->getMorphClass(), 'source_id' => $income->id]))->toThrow(ValidationException::class);
    expect($wallet->fresh()->nominal)->toBe('0.00');
});

it('checks the historical year end BOS balance rather than a later positive current balance', function () {
    [$school, $user, $account] = bosClosingFixture();
    $account->update(['opening_balance' => -100, 'current_balance' => -100]);
    bosClosingIncome($school, $account, ['bos_year' => 2026, 'received_date' => '2026-01-10', 'amount' => 200]);
    $preview = app(BosClosingService::class)->preview($school, 'BOS Reguler', 2025);
    expect($preview['validation']['negative_balances'])->toBe(1)->and($preview['validation']['all_passed'])->toBeFalse()
        ->and($preview['accounts'][0]['balance'])->toEqual(-100);
});
