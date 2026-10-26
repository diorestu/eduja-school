<?php

use App\Models\School;
use App\Models\SchoolRolePermission;
use App\Models\SchoolUserRole;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function loginFlowSchool(User $user, string $name, array $roles = ['guru']): School
{
    $school = School::create(['name' => $name, 'status' => 'active', 'is_active' => true]);
    foreach ($roles as $role) {
        $school->roles()->create(['user_id' => $user->id, 'role' => $role, 'is_active' => true, 'membership_status' => 'active']);
    }

    return $school;
}

it('automatically selects one unique school even when the user has multiple roles', function () {
    $user = User::factory()->create(['role' => 'guru']);
    $school = loginFlowSchool($user, 'Sekolah A', ['guru', 'bendahara']);

    $this->post('/signin', ['login' => strtoupper($user->email), 'password' => 'password'])
        ->assertRedirect('/dashboard')->assertSessionHas('active_school_id', $school->id);
    $this->assertAuthenticatedAs($user);
});

it('requires school selection after login despite an intended url and stale context', function () {
    $user = User::factory()->create(['role' => 'guru']);
    $first = loginFlowSchool($user, 'Sekolah A');
    loginFlowSchool($user, 'Sekolah B');

    $this->withSession(['active_school_id' => $first->id, 'url.intended' => '/dashboard'])
        ->post('/signin', ['login' => $user->email, 'password' => 'password'])
        ->assertRedirect('/school/select')->assertSessionMissing('active_school_id')->assertSessionMissing('url.intended');
    $this->get('/dashboard')->assertRedirect('/school/select');
});

it('enters the dashboard after selecting a school instead of a stored destination', function () {
    $user = User::factory()->create(['role' => 'guru']);
    $first = loginFlowSchool($user, 'Sekolah A');
    $second = loginFlowSchool($user, 'Sekolah B', ['bendahara']);

    $this->actingAs($user)->withSession(['active_school_id' => $first->id, 'url.intended' => '/profile'])
        ->post('/school/switch', ['school_id' => $second->id])
        ->assertRedirect('/dashboard')->assertSessionHas('active_school_id', $second->id)->assertSessionMissing('url.intended');
    expect($user->hasRole('bendahara'))->toBeTrue()->and($user->hasRole('guru'))->toBeFalse();
});

it('blocks school data without an active affiliation and shows only the users pending request', function () {
    $user = User::factory()->create(['role' => 'super_admin']);
    $pending = loginFlowSchool($user, 'Sekolah Menunggu');
    $pending->roles()->update(['membership_status' => 'pending']);
    $otherUser = User::factory()->create();
    $other = loginFlowSchool($otherUser, 'Pengajuan Pengguna Lain');
    $other->roles()->update(['membership_status' => 'pending']);

    $this->withSession(['url.intended' => '/dashboard'])->post('/signin', ['login' => $user->email, 'password' => 'password'])
        ->assertRedirect('/school/select')->assertSessionMissing('active_school_id');
    $this->get('/school/select')->assertOk()->assertSee('Belum memiliki akses sekolah')
        ->assertSee('Sekolah Menunggu')->assertDontSee('Pengajuan Pengguna Lain');
    $this->get('/dashboard')->assertRedirect('/school/select');
    $this->get('/finance/accounts')->assertRedirect('/school/select');
    expect($user->hasRole('super_admin'))->toBeFalse();
});

it('rejects selecting a school without an active eligible affiliation', function (string $state) {
    $user = User::factory()->create(['role' => 'guru']);
    $current = loginFlowSchool($user, 'Sekolah Saat Ini');
    $other = loginFlowSchool($user, 'Sekolah Ditolak');
    match ($state) {
        'foreign' => $other->roles()->update(['user_id' => User::factory()->create()->id]),
        'pending' => $other->roles()->update(['membership_status' => 'pending']),
        'revoked' => $other->roles()->update(['is_active' => false]),
        'suspended' => $other->update(['status' => 'suspended']),
        'inactive' => $other->update(['is_active' => false]),
    };

    $this->actingAs($user)->withSession(['active_school_id' => $current->id])
        ->post('/school/switch', ['school_id' => $other->id])->assertForbidden()
        ->assertSessionHas('active_school_id', $current->id);
})->with(['foreign', 'pending', 'revoked', 'suspended', 'inactive']);

it('clears a selected school whose access has become invalid', function (string $state) {
    $user = User::factory()->create(['role' => 'super_admin']);
    $school = loginFlowSchool($user, 'Sekolah Lama', ['kepsek']);
    loginFlowSchool($user, 'Sekolah Lain');
    match ($state) {
        'deleted' => $school->roles()->delete(),
        'pending' => $school->roles()->update(['membership_status' => 'pending']),
        'revoked' => $school->roles()->update(['is_active' => false]),
        'suspended' => $school->update(['status' => 'suspended']),
        'inactive' => $school->update(['is_active' => false]),
    };

    $this->actingAs($user)->withSession(['active_school_id' => $school->id])
        ->get('/dashboard')->assertRedirect('/school/select')->assertSessionMissing('active_school_id');
})->with(['deleted', 'pending', 'revoked', 'suspended', 'inactive']);

it('combines active membership permissions only within the selected school', function () {
    $user = User::factory()->create(['role' => 'super_admin']);
    $school = loginFlowSchool($user, 'Sekolah Gabungan', ['guru', 'bendahara']);
    $school->roles()->create(['user_id' => $user->id, 'role' => 'kepsek', 'is_active' => true, 'membership_status' => 'pending']);
    loginFlowSchool($user, 'Sekolah Lain', ['tu']);
    session(['active_school_id' => $school->id]);

    $permissions = app(PermissionService::class);
    expect($user->hasRole('guru'))->toBeTrue()
        ->and($user->hasRole('bendahara'))->toBeTrue()
        ->and($permissions->can($user, 'finance.accounts', ['bendahara']))->toBeTrue()
        ->and($user->hasRole('super_admin'))->toBeFalse()
        ->and($user->hasRole('tu'))->toBeFalse();

    SchoolUserRole::where('user_id', $user->id)->where('school_id', $school->id)->update(['is_active' => false]);
    expect($permissions->can($user, 'finance.accounts', ['bendahara']))->toBeFalse()
        ->and($user->hasRole('super_admin'))->toBeFalse();
});

it('does not grant school permissions for a suspended school or missing selection', function () {
    $user = User::factory()->create(['role' => 'super_admin']);
    $school = loginFlowSchool($user, 'Sekolah Nonaktif', ['kepsek']);
    $school->update(['status' => 'suspended']);
    session(['active_school_id' => $school->id]);

    expect($user->hasRole('super_admin'))->toBeFalse();
    session()->forget('active_school_id');
    expect($user->hasRole('super_admin'))->toBeFalse();
});

it('keeps executive login independent of school context and stored destinations', function (string $role, string $dashboard) {
    $user = User::factory()->create(['role' => $role]);
    $first = loginFlowSchool($user, 'Sekolah A');
    loginFlowSchool($user, 'Sekolah B');

    $this->withSession(['active_school_id' => $first->id, 'url.intended' => '/dashboard'])
        ->post('/signin', ['login' => $user->email, 'password' => 'password'])
        ->assertRedirect($dashboard)->assertSessionMissing('active_school_id');
    $this->get($dashboard)->assertOk();
    $this->get('/school/select')->assertRedirect($dashboard);
})->with([['dinas', '/dinas'], ['yayasan', '/yayasan']]);

it('rejects invalid passwords without logging in or selecting a school', function () {
    $user = User::factory()->create(['role' => 'guru']);
    loginFlowSchool($user, 'Sekolah A');

    $this->from('/signin')->post('/signin', ['login' => $user->email, 'password' => 'wrong'])
        ->assertRedirect('/signin')->assertSessionHasErrors('email')->assertSessionMissing('active_school_id');
    $this->assertGuest();
});

it('routes the selected schools role to its existing dashboard', function (string $role, string $dashboard) {
    $user = User::factory()->create(['role' => 'guru']);
    $school = loginFlowSchool($user, 'Sekolah Portal', [$role]);

    $this->post('/signin', ['login' => $user->email, 'password' => 'password'])
        ->assertRedirect('/dashboard')->assertSessionHas('active_school_id', $school->id);
    $this->get('/dashboard')->assertRedirect($dashboard);
})->with([['siswa', '/portal/siswa'], ['orang_tua', '/portal/orang-tua'], ['alumni', '/alumni']]);

it('loads additional grants from the selected school after switching', function () {
    $user = User::factory()->create(['role' => 'guru']);
    $first = loginFlowSchool($user, 'Sekolah Grant A');
    $second = loginFlowSchool($user, 'Sekolah Grant B');
    SchoolRolePermission::create(['school_id' => $first->id, 'role' => 'guru', 'permission' => 'finance.accounts', 'is_allowed' => true]);

    $this->actingAs($user)->withSession(['active_school_id' => $first->id])->get('/finance/accounts')->assertOk();
    $this->post('/school/switch', ['school_id' => $second->id])->assertRedirect('/dashboard');
    $this->get('/finance/accounts')->assertForbidden();
});

it('shows the empty access state for a user without any affiliations', function () {
    $user = User::factory()->create(['role' => 'bendahara']);

    $this->post('/signin', ['login' => $user->email, 'password' => 'password'])->assertRedirect('/school/select');
    $this->get('/school/select')->assertOk()->assertViewHas('schools', fn ($schools) => $schools->isEmpty())
        ->assertViewHas('pendingSchoolNames', fn ($names) => $names->isEmpty());
    $this->get('/dashboard')->assertRedirect('/school/select');
    $this->post('/finance/accounts', ['name' => 'Tidak Diizinkan'])->assertForbidden();
    $this->assertDatabaseCount('school_accounts', 0);
});

it('opens the executive dashboard without any school membership', function (string $role, string $dashboard) {
    $user = User::factory()->create(['role' => $role]);

    $this->post('/signin', ['login' => $user->email, 'password' => 'password'])->assertRedirect($dashboard);
    $this->get($dashboard)->assertOk();
    $this->post('/school/switch', ['school_id' => 999])->assertRedirect($dashboard)->assertSessionMissing('active_school_id');
})->with([['dinas', '/dinas'], ['yayasan', '/yayasan']]);
