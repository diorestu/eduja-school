<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('creates a pending approval when bendahara records an expense', function () {
    $bendahara = User::factory()->create(['role' => 'bendahara']);
    $school = DB::table('schools')->insertGetId(['name' => 'Sekolah Approval', 'level' => 'sma', 'ownership' => 'swasta', 'created_at' => now(), 'updated_at' => now()]);
    $year = DB::table('academic_years')->insertGetId(['school_id' => $school, 'year' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('school_user_roles')->insert(['user_id' => $bendahara->id, 'school_id' => $school, 'role' => 'bendahara', 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($bendahara)->withSession(['active_school_id' => $school])->post('/bos/belanja', [
        'academic_year_id' => $year, 'expense_name' => 'ATK', 'amount' => 100000,
        'transaction_date' => now()->toDateString(), 'source_funding' => 'BOS', 'payment_method' => 'Tunai',
    ])->assertRedirect();

    $expense = DB::table('expenses')->where('school_id', $school)->first();
    expect($expense->status)->toBe('pending');
    expect(DB::table('approval_requests')->where('approvable_id', $expense->id)->where('type', 'expense')->value('status'))->toBe('pending');
});

it('allows only the school principal role to approve an expense', function () {
    $bendahara = User::factory()->create(['role' => 'bendahara']);
    $kepsek = User::factory()->create(['role' => 'super_admin']);
    $school = DB::table('schools')->insertGetId(['name' => 'Sekolah Review', 'level' => 'sma', 'ownership' => 'swasta', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('school_user_roles')->insert([
        ['user_id' => $bendahara->id, 'school_id' => $school, 'role' => 'bendahara', 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $kepsek->id, 'school_id' => $school, 'role' => 'kepsek', 'created_at' => now(), 'updated_at' => now()],
    ]);
    $year = DB::table('academic_years')->insertGetId(['school_id' => $school, 'year' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $expense = DB::table('expenses')->insertGetId(['school_id' => $school, 'academic_year_id' => $year, 'expense_name' => 'BOS', 'amount' => 200000, 'transaction_date' => now()->toDateString(), 'source_funding' => 'BOS', 'payment_method' => 'Tunai', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
    $approval = DB::table('approval_requests')->insertGetId(['school_id' => $school, 'requested_by' => $bendahara->id, 'approvable_type' => App\Models\Expense::class, 'approvable_id' => $expense, 'type' => 'expense', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($bendahara)->withSession(['active_school_id' => $school])->post("/approvals/{$approval}/approve")->assertForbidden();
    $this->actingAs($kepsek)->withSession(['active_school_id' => $school])->post("/approvals/{$approval}/approve")->assertRedirect();
    expect(DB::table('expenses')->where('id', $expense)->value('status'))->toBe('approved');
});
