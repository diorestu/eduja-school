<?php

use App\Models\User;
use App\Models\SchoolAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->schoolId = DB::table('schools')->insertGetId([
        'name' => 'Sekolah Rekening', 'level' => 'sma', 'ownership' => 'swasta',
        'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $user = User::factory()->create(['role' => 'bendahara']);
    DB::table('school_user_roles')->insert([
        'user_id' => $user->id, 'school_id' => $this->schoolId, 'role' => 'bendahara',
        'is_active' => true, 'membership_status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->actingAs($user)->withSession(['active_school_id' => $this->schoolId]);
});

it('shows the school account form and excludes accounts from another school', function () {
    $other = DB::table('schools')->insertGetId([
        'name' => 'Sekolah Lain', 'level' => 'sma', 'ownership' => 'swasta',
        'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    SchoolAccount::create(['school_id' => $other, 'name' => 'Rekening Rahasia', 'type' => 'Tunai']);
    $this->get(route('finance.accounts'))->assertOk()->assertSee('+ Rekening Sekolah')
        ->assertSee('Nominal saat ini')->assertDontSee('Rekening Rahasia');
});

it('saves bank details and initializes both balances', function () {
    $this->post(route('finance.accounts.store'), [
        'name' => 'Rekening Operasional', 'type' => 'Bank', 'bank_name' => 'Bank Sekolah',
        'account_number' => '0012345678', 'opening_balance' => '123456.78',
    ])->assertRedirect(route('finance.accounts'))->assertSessionHas('success', 'Rekening Sekolah disimpan');
    $this->assertDatabaseHas('school_accounts', [
        'school_id' => $this->schoolId, 'account_number' => '0012345678',
        'opening_balance' => 123456.78, 'current_balance' => 123456.78,
    ]);
});

it('discards bank details for cash accounts', function () {
    $this->post(route('finance.accounts.store'), [
        'name' => 'Kas', 'type' => 'Tunai', 'bank_name' => 'Tidak Dipakai',
        'account_number' => '123', 'opening_balance' => 0,
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('school_accounts', [
        'school_id' => $this->schoolId, 'name' => 'Kas', 'bank_name' => null,
        'account_number' => null, 'current_balance' => 0,
    ]);
});

it('rejects incomplete bank details and invalid balances without saving', function () {
    $this->post(route('finance.accounts.store'), [
        'name' => 'Bank', 'type' => 'Bank', 'opening_balance' => -1,
    ])->assertSessionHasErrors(['bank_name', 'account_number', 'opening_balance']);
    $this->assertDatabaseCount('school_accounts', 0);
});

it('rejects account types outside the form choices', function () {
    $this->post(route('finance.accounts.store'), [
        'name' => 'Invalid', 'type' => 'Other', 'opening_balance' => 0,
    ])->assertSessionHasErrors('type');
    $this->assertDatabaseCount('school_accounts', 0);
});
