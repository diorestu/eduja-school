<?php

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function phaseOnePortalSchool(User $user, string $role = 'siswa'): School
{
    $school = School::create(['name' => 'Sekolah Portal', 'status' => 'active']);
    DB::table('school_user_roles')->insert(['user_id' => $user->id, 'school_id' => $school->id, 'role' => $role, 'is_active' => true, 'membership_status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    return $school;
}

it('does not fabricate or borrow a student identity for an unlinked student account', function () {
    $user = User::factory()->create(['role' => 'siswa']);
    $school = phaseOnePortalSchool($user);
    Student::create(['school_id' => $school->id, 'nis' => 'OTHER-001', 'name' => 'Siswa Milik Orang Lain', 'gender' => 'L']);

    $this->actingAs($user)->withSession(['active_school_id' => $school->id])->get('/portal/siswa')
        ->assertViewIs('pages.portal.empty-identity');
});

it('returns only guardian-owned students in the parent portal', function () {
    $guardian = User::factory()->create(['role' => 'orang_tua']);
    $school = phaseOnePortalSchool($guardian, 'orang_tua');
    $owned = Student::create(['school_id' => $school->id, 'guardian_user_id' => $guardian->id, 'nis' => 'OWN-001', 'name' => 'Anak Terhubung', 'gender' => 'P']);
    Student::create(['school_id' => $school->id, 'nis' => 'OTHER-002', 'name' => 'Anak Orang Lain', 'gender' => 'L']);

    $this->actingAs($guardian)->withSession(['active_school_id' => $school->id])->get('/portal/orang-tua')
        ->assertViewHas('students', fn ($students) => $students->pluck('id')->all() === [$owned->id]);
});
