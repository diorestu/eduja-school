<?php

use App\Helpers\MenuHelper;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates a school scoped role permission table', function () {
    expect(Schema::hasTable('school_role_permissions'))->toBeTrue();
    expect(Schema::hasColumns('school_role_permissions', [
        'school_id',
        'role',
        'permission',
        'is_allowed',
    ]))->toBeTrue();
});

function createPermissionSchool(string $name): int
{
    return DB::table('schools')->insertGetId([
        'name' => $name,
        'level' => 'sma',
        'ownership' => 'swasta',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function attachPermissionRole(User $user, int $schoolId, string $role): void
{
    DB::table('school_user_roles')->insert([
        'school_id' => $schoolId,
        'user_id' => $user->id,
        'role' => $role,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('keeps default role access without an additional grant', function () {
    $user = User::factory()->create(['role' => 'staf_tu']);
    $schoolId = createPermissionSchool('Sekolah Default Role');
    attachPermissionRole($user, $schoolId, 'staf_tu');
    session(['active_school_id' => $schoolId]);

    expect(app(PermissionService::class)->can($user, 'students.view', ['staf_tu']))->toBeTrue();
});

it('grants an additional permission to a role in the active school', function () {
    $user = User::factory()->create(['role' => 'guru']);
    $schoolId = createPermissionSchool('Sekolah Grant');
    attachPermissionRole($user, $schoolId, 'guru');
    DB::table('school_role_permissions')->insert([
        'school_id' => $schoolId,
        'role' => 'guru',
        'permission' => 'students.view',
        'is_allowed' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    session(['active_school_id' => $schoolId]);

    expect(app(PermissionService::class)->can($user, 'students.view', ['staf_tu']))->toBeTrue();
});

it('does not leak additional permissions between schools', function () {
    $user = User::factory()->create(['role' => 'guru']);
    $schoolA = createPermissionSchool('Sekolah Grant A');
    $schoolB = createPermissionSchool('Sekolah Grant B');
    attachPermissionRole($user, $schoolA, 'guru');
    attachPermissionRole($user, $schoolB, 'guru');
    DB::table('school_role_permissions')->insert([
        'school_id' => $schoolA,
        'role' => 'guru',
        'permission' => 'students.view',
        'is_allowed' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    session(['active_school_id' => $schoolB]);

    expect(app(PermissionService::class)->can($user, 'students.view', ['staf_tu']))->toBeFalse();
});

it('keeps school administrators as full access users', function () {
    $user = User::factory()->create(['role' => 'super_admin']);
    $schoolId = createPermissionSchool('Sekolah Admin');
    attachPermissionRole($user, $schoolId, 'kepsek');
    session(['active_school_id' => $schoolId]);

    expect(app(PermissionService::class)->can($user, 'any.permission', []))->toBeTrue();
});

it('shows only additionally granted submenus from a restricted menu group', function () {
    $user = User::factory()->create(['role' => 'guru']);
    $schoolId = createPermissionSchool('Sekolah Menu Grant');
    attachPermissionRole($user, $schoolId, 'guru');
    DB::table('school_role_permissions')->insert([
        'school_id' => $schoolId,
        'role' => 'guru',
        'permission' => 'students.view',
        'is_allowed' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)->withSession(['active_school_id' => $schoolId]);

    $studentGroup = collect(MenuHelper::getMainNavItems())->firstWhere('name', 'Kesiswaan');

    expect($studentGroup)->not->toBeNull();
    expect(collect($studentGroup['subItems'])->pluck('path')->all())->toBe(['/siswa']);
});

it('allows a direct route through an additional school permission', function () {
    $user = User::factory()->create(['role' => 'guru']);
    $schoolId = createPermissionSchool('Sekolah Route Grant');
    attachPermissionRole($user, $schoolId, 'guru');
    DB::table('school_role_permissions')->insert([
        'school_id' => $schoolId,
        'role' => 'guru',
        'permission' => 'students.view',
        'is_allowed' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['active_school_id' => $schoolId])
        ->get('/siswa')
        ->assertOk();
});

it('rejects direct routes without a default role or additional permission', function () {
    $user = User::factory()->create(['role' => 'guru']);
    $schoolId = createPermissionSchool('Sekolah Route Ditolak');
    attachPermissionRole($user, $schoolId, 'guru');

    $this->actingAs($user)
        ->withSession(['active_school_id' => $schoolId])
        ->get('/siswa')
        ->assertForbidden();
});

it('allows only school administrators to open permission settings', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $teacher = User::factory()->create(['role' => 'guru']);
    $schoolId = createPermissionSchool('Sekolah Pengaturan Permission');
    attachPermissionRole($admin, $schoolId, 'kepsek');
    attachPermissionRole($teacher, $schoolId, 'guru');

    $this->actingAs($admin)
        ->withSession(['active_school_id' => $schoolId])
        ->get('/settings/permissions')
        ->assertOk()
        ->assertSee('Permission Tambahan');

    $this->actingAs($teacher)
        ->withSession(['active_school_id' => $schoolId])
        ->get('/settings/permissions')
        ->assertForbidden();
});

it('syncs additional permissions only for the selected role and active school', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $schoolA = createPermissionSchool('Sekolah Sinkron A');
    $schoolB = createPermissionSchool('Sekolah Sinkron B');
    attachPermissionRole($admin, $schoolA, 'kepsek');
    attachPermissionRole($admin, $schoolB, 'kepsek');

    DB::table('school_role_permissions')->insert([
        [
            'school_id' => $schoolA,
            'role' => 'guru',
            'permission' => 'finance.accounts',
            'is_allowed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'school_id' => $schoolB,
            'role' => 'guru',
            'permission' => 'finance.accounts',
            'is_allowed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->actingAs($admin)
        ->withSession(['active_school_id' => $schoolA])
        ->put('/settings/permissions', [
            'role' => 'guru',
            'permissions' => ['students.view', 'spp.reports'],
        ])
        ->assertRedirect('/settings/permissions?role=guru')
        ->assertSessionHas('success');

    expect(DB::table('school_role_permissions')->where('school_id', $schoolA)->where('role', 'guru')->pluck('permission')->sort()->values()->all())
        ->toBe(['spp.reports', 'students.view']);
    expect(DB::table('school_role_permissions')->where('school_id', $schoolB)->where('role', 'guru')->pluck('permission')->all())
        ->toBe(['finance.accounts']);
});

it('rejects permission keys outside the menu catalog', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $schoolId = createPermissionSchool('Sekolah Validasi Permission');
    attachPermissionRole($admin, $schoolId, 'kepsek');

    $this->actingAs($admin)
        ->withSession(['active_school_id' => $schoolId])
        ->from('/settings/permissions?role=guru')
        ->put('/settings/permissions', [
            'role' => 'guru',
            'permissions' => ['system.destroy'],
        ])
        ->assertRedirect('/settings/permissions?role=guru')
        ->assertSessionHasErrors('permissions.0');

    expect(DB::table('school_role_permissions')->where('school_id', $schoolId)->exists())->toBeFalse();
});
