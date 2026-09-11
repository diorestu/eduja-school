<?php

use App\Models\School;
use App\Models\User;
use App\Services\RoleRedirectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('routes executive and school users to the correct context', function () {
    $dinas = User::factory()->create(['role' => 'dinas']);
    $yayasan = User::factory()->create(['role' => 'yayasan']);
    $schoolUser = User::factory()->create(['role' => 'guru']);
    $schoolA = School::create(['name' => 'Sekolah A', 'status' => 'active']);
    $schoolB = School::create(['name' => 'Sekolah B', 'status' => 'active']);
    DB::table('school_user_roles')->insert([
        ['user_id' => $schoolUser->id, 'school_id' => $schoolA->id, 'role' => 'guru', 'is_active' => true, 'membership_status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $schoolUser->id, 'school_id' => $schoolB->id, 'role' => 'guru', 'is_active' => true, 'membership_status' => 'active', 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(app(RoleRedirectService::class)->afterLogin($dinas))->toBe('dinas.dashboard')
        ->and(app(RoleRedirectService::class)->afterLogin($yayasan))->toBe('yayasan.dashboard')
        ->and(app(RoleRedirectService::class)->afterLogin($schoolUser))->toBe('school.select');
});

it('routes a school-scoped user without membership to the safe school state', function () {
    $user = User::factory()->create(['role' => 'guru']);

    expect(app(RoleRedirectService::class)->afterLogin($user))->toBe('school.select');
});
