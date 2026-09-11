<?php

use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\IdentityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('provides explicit login and person identity fields', function () {
    expect(Schema::hasColumns('users', ['phone', 'phone_normalized', 'onboarding_status']))->toBeTrue()
        ->and(Schema::hasColumns('students', ['user_id', 'guardian_user_id']))->toBeTrue()
        ->and(Schema::hasColumns('teachers', ['user_id']))->toBeTrue()
        ->and(Schema::hasColumns('school_user_roles', ['membership_status']))->toBeTrue();
});

it('resolves only linked people in the requested school', function () {
    $user = User::factory()->create();
    $school = School::create(['name' => 'Sekolah Identity', 'status' => 'active']);
    $otherSchool = School::create(['name' => 'Sekolah Lain', 'status' => 'active']);
    $student = Student::create(['school_id' => $school->id, 'user_id' => $user->id, 'nis' => 'ID-001', 'name' => 'Siswa Terhubung', 'gender' => 'L']);
    Student::create(['school_id' => $otherSchool->id, 'user_id' => $user->id, 'nis' => 'ID-002', 'name' => 'Siswa Sekolah Lain', 'gender' => 'L']);
    $teacher = Teacher::create(['school_id' => $school->id, 'user_id' => $user->id, 'name' => 'Guru Terhubung', 'role_type' => 'Guru']);

    expect(app(IdentityResolver::class)->studentFor($user, $school->id)->is($student))->toBeTrue()
        ->and(app(IdentityResolver::class)->teacherFor($user, $school->id)->is($teacher))->toBeTrue()
        ->and(app(IdentityResolver::class)->studentFor($user, 999999))->toBeNull();
});
