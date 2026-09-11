<?php

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('has onboarding and guardian ownership fields', function () {
    expect(Schema::hasColumns('users', ['registration_type', 'onboarding_status']))->toBeTrue()
        ->and(Schema::hasColumns('schools', ['status', 'registration_code']))->toBeTrue()
        ->and(Schema::hasColumns('school_user_roles', ['membership_status']))->toBeTrue()
        ->and(Schema::hasColumns('students', ['guardian_user_id']))->toBeTrue();
});

it('registers a guardian as active with a selected school code and owns the student', function () {
    $school = School::create(['name' => 'Sekolah Budi', 'status' => 'active', 'registration_code' => 'EDUJA-ABC123']);

    $response = $this->post('/signup', [
        'registration_type' => 'wali_murid',
        'fname' => 'Budi', 'lname' => 'Santoso',
        'email' => 'budi@example.test', 'password' => 'password',
        'school_code' => 'EDUJA-ABC123',
    ]);

    $response->assertRedirect();
    $user = User::where('email', 'budi@example.test')->firstOrFail();
    expect($user->role)->toBe('orang_tua')->and($user->onboarding_status)->toBe('active');
    expect($user->schools()->whereKey($school->id)->exists())->toBeTrue();

    $student = Student::create([
        'school_id' => $school->id, 'guardian_user_id' => $user->id,
        'nis' => 'WALI-001', 'name' => 'Siswa Budi', 'gender' => 'L',
    ]);
    expect($student->guardian_user_id)->toBe($user->id);
});

it('does not allow a non-superadmin to access permission settings', function () {
    $pic = User::factory()->create(['role' => 'pic_sekolah']);
    $this->actingAs($pic)->get('/settings/permissions')->assertForbidden();
});

it('gives an active PIC operational access without superadmin access', function () {
    $pic = User::factory()->create(['role' => 'pic_sekolah']);
    $school = School::create(['name' => 'PIC School', 'status' => 'active', 'registration_code' => 'PIC-001']);
    $school->roles()->create(['user_id' => $pic->id, 'role' => 'pic_sekolah', 'is_active' => true, 'membership_status' => 'active']);
    session(['active_school_id' => $school->id]);

    expect(app(\App\Services\PermissionService::class)->can($pic, 'students.view'))->toBeTrue()
        ->and(app(\App\Services\PermissionService::class)->can($pic, 'settings.permissions'))->toBeFalse();
});
