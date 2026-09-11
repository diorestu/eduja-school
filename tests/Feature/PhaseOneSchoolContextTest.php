<?php

use App\Models\School;
use App\Models\User;
use App\Services\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('returns only active schools with active memberships', function () {
    $user = User::factory()->create();
    $active = School::create(['name' => 'Sekolah Aktif', 'status' => 'active', 'is_active' => true]);
    $inactiveSchool = School::create(['name' => 'Sekolah Nonaktif', 'status' => 'suspended', 'is_active' => false]);
    $pendingMembership = School::create(['name' => 'Sekolah Menunggu', 'status' => 'active', 'is_active' => true]);
    DB::table('school_user_roles')->insert([
        ['user_id' => $user->id, 'school_id' => $active->id, 'role' => 'guru', 'is_active' => true, 'membership_status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $user->id, 'school_id' => $inactiveSchool->id, 'role' => 'guru', 'is_active' => true, 'membership_status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $user->id, 'school_id' => $pendingMembership->id, 'role' => 'guru', 'is_active' => true, 'membership_status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(app(SchoolContext::class)->availableSchools($user)->pluck('id')->all())->toBe([$active->id]);
});

it('validates membership before changing the active school', function () {
    $user = User::factory()->create();
    $school = School::create(['name' => 'Sekolah Tujuan', 'status' => 'active', 'is_active' => true]);

    expect(fn () => app(SchoolContext::class)->setActiveSchool($user, $school->id))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('renders school details and a header switcher for multi-school users', function () {
    $user = User::factory()->create(['role' => 'guru']);
    $first = School::create(['name' => 'Sekolah A', 'status' => 'active', 'city' => 'Makassar']);
    $second = School::create(['name' => 'Sekolah B', 'status' => 'active', 'city' => 'Gowa']);
    foreach ([$first, $second] as $school) {
        DB::table('school_user_roles')->insert(['user_id' => $user->id, 'school_id' => $school->id, 'role' => 'guru', 'is_active' => true, 'membership_status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    $this->actingAs($user)->get('/school/select')->assertOk()->assertSee('Makassar')->assertSee('Ganti sekolah');
});
