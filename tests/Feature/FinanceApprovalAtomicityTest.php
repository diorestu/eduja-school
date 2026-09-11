<?php

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('keeps approval and expense pending when the approvable belongs to another school', function () {
    $reviewer = User::factory()->create(['role' => 'super_admin']);
    $schoolA = DB::table('schools')->insertGetId(['name' => 'Sekolah A', 'level' => 'sma', 'ownership' => 'swasta', 'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $schoolB = DB::table('schools')->insertGetId(['name' => 'Sekolah B', 'level' => 'sma', 'ownership' => 'swasta', 'status' => 'active', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('school_user_roles')->insert(['user_id' => $reviewer->id, 'school_id' => $schoolA, 'role' => 'kepsek', 'is_active' => true, 'membership_status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    $year = DB::table('academic_years')->insertGetId(['school_id' => $schoolB, 'year' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $expense = DB::table('expenses')->insertGetId(['school_id' => $schoolB, 'academic_year_id' => $year, 'expense_name' => 'Lintas sekolah', 'amount' => 100, 'transaction_date' => now()->toDateString(), 'source_funding' => 'BOS', 'payment_method' => 'Tunai', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
    $approval = DB::table('approval_requests')->insertGetId(['school_id' => $schoolA, 'requested_by' => $reviewer->id, 'approvable_type' => Expense::class, 'approvable_id' => $expense, 'type' => 'expense', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

    $this->actingAs($reviewer)->withSession(['active_school_id' => $schoolA])->post("/approvals/{$approval}/approve")->assertForbidden();

    expect(DB::table('approval_requests')->where('id', $approval)->value('status'))->toBe('pending')
        ->and(DB::table('expenses')->where('id', $expense)->value('status'))->toBe('pending');
});
