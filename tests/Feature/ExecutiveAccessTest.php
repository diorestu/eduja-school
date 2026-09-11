<?php

use App\Models\User;
use App\Models\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes each executive dashboard only to its matching role', function () {
    $dinas = User::factory()->create(['role' => 'dinas']);
    $yayasan = User::factory()->create(['role' => 'yayasan']);
    $schoolAdmin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($dinas)->get('/dinas')->assertOk();
    $this->actingAs($dinas)->get('/yayasan')->assertForbidden();
    $this->actingAs($yayasan)->get('/yayasan')->assertOk();
    $this->actingAs($yayasan)->get('/dinas')->assertForbidden();
    $this->actingAs($schoolAdmin)->get('/dinas')->assertForbidden();
    $this->actingAs($schoolAdmin)->get('/yayasan')->assertForbidden();
});

it('does not show executive dashboards to school administrators', function () {
    $this->actingAs(User::factory()->create(['role' => 'super_admin']))->get('/dashboard')->assertOk();
    expect(collect(\App\Helpers\MenuHelper::getMainNavItems())->pluck('name')->all())
        ->not->toContain('Dashboard Dinas')
        ->not->toContain('Dashboard Yayasan');
});

it('provides district dashboard breakdowns and six month chart series', function () {
    $school = School::create(['name' => 'SD Negeri 1', 'level' => 'sd', 'ownership' => 'negeri', 'is_active' => true]);
    DB::table('teachers')->insert([
        ['school_id' => $school->id, 'name' => 'Guru 1', 'role_type' => 'Guru', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['school_id' => $school->id, 'name' => 'Tendik 1', 'role_type' => 'Staf TU', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    $response = $this->actingAs(User::factory()->create(['role' => 'dinas']))->get('/dinas');
    $response->assertOk()->assertViewHas('districtDashboard', fn ($data) => $data['schoolBreakdown']['sd_mi'] === 1 && $data['people']['teachers'] === 1 && $data['people']['staff'] === 1 && count($data['months']) === 6);
});

it('loads a foundation aggregate without selecting a school and blocks other foundations', function () {
    $foundation = User::factory()->create(['role' => 'yayasan']);
    $owned = School::create(['name' => 'Sekolah Yayasan A', 'foundation_name' => 'Yayasan A', 'is_active' => true]);
    $other = School::create(['name' => 'Sekolah Yayasan B', 'foundation_name' => 'Yayasan B', 'is_active' => true]);
    DB::table('school_user_roles')->insert([
        'user_id' => $foundation->id, 'school_id' => $owned->id, 'role' => 'yayasan', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($foundation)->get('/yayasan')->assertOk()->assertViewHas('foundationSchools', fn ($schools) => $schools->pluck('id')->all() === [$owned->id]);
    $this->actingAs($foundation)->get('/yayasan/sekolah/'.$owned->id)->assertOk();
    $this->actingAs($foundation)->get('/yayasan/sekolah/'.$other->id)->assertForbidden();
});
