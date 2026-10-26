<?php

use App\Models\AcademicYear;
use App\Models\Expense;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function districtSchool(string $name, bool $allowed = false): School
{
    $school = School::create(['name' => $name, 'level' => 'sd', 'ownership' => 'negeri', 'status' => 'active', 'is_active' => true]);
    if ($allowed) {
        $school->dinas_finance_allowed = true;
        $school->save();
    }

    return $school;
}

function districtRole(User $user, School $school, string $role, string $status = 'active'): void
{
    $school->roles()->create(['user_id' => $user->id, 'role' => $role, 'is_active' => true, 'membership_status' => $status]);
}

function districtExpense(School $school, float $amount, string $status = 'approved', string $source = 'BOS'): void
{
    $year = AcademicYear::firstOrCreate(['school_id' => $school->id, 'year' => '2026/2027', 'semester' => 'Ganjil'],
        ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    Expense::create(['school_id' => $school->id, 'academic_year_id' => $year->id, 'expense_name' => 'Belanja Sekolah', 'amount' => $amount,
        'transaction_date' => '2026-10-10', 'source_funding' => $source, 'status' => $status, 'payment_method' => 'Tunai']);
}

it('stores school finance consent disabled by default', function () {
    expect(Schema::hasColumn('schools', 'dinas_finance_allowed'))->toBeTrue();
    expect(districtSchool('Default')->fresh()->dinas_finance_allowed)->toBeFalse();
});

it('allows the active kepala sekolah to grant and revoke finance consent for its own school only', function () {
    $head = User::factory()->create(['role' => 'guru']);
    $school = districtSchool('Sekolah Sendiri');
    $other = districtSchool('Sekolah Lain');
    districtRole($head, $school, 'kepsek');
    $this->actingAs($head)->withSession(['active_school_id' => $school->id]);

    $this->get('/settings/dinas-access')->assertOk()->assertSee('Sekolah Sendiri');
    $this->put('/settings/dinas-access', ['dinas_finance_allowed' => '1', 'school_id' => $other->id])
        ->assertRedirect('/settings/dinas-access')->assertSessionHas('success');
    expect($school->fresh()->dinas_finance_allowed)->toBeTrue()->and($other->fresh()->dinas_finance_allowed)->toBeFalse();
    $this->put('/settings/dinas-access', ['dinas_finance_allowed' => '0'])->assertRedirect('/settings/dinas-access');
    expect($school->fresh()->dinas_finance_allowed)->toBeFalse();
});

it('denies consent changes without an active kepala sekolah membership', function (string $role, string $status) {
    $user = User::factory()->create(['role' => 'super_admin']);
    $school = districtSchool('Sekolah Terbatas');
    districtRole($user, $school, $role, $status);
    $this->actingAs($user)->withSession(['active_school_id' => $school->id])
        ->put('/settings/dinas-access', ['dinas_finance_allowed' => true])->assertForbidden();
    expect($school->fresh()->dinas_finance_allowed)->toBeFalse();
})->with([['bendahara', 'active'], ['guru', 'active'], ['pic_sekolah', 'active'], ['kepsek', 'pending']]);

it('denies Dinas permission to grant finance consent even with a selected school', function () {
    $user = User::factory()->create(['role' => 'dinas']);
    $school = districtSchool('Sekolah');
    $this->actingAs($user)->withSession(['active_school_id' => $school->id])
        ->put('/settings/dinas-access', ['dinas_finance_allowed' => true])->assertForbidden();
    expect($school->fresh()->dinas_finance_allowed)->toBeFalse();
});

it('filters the school dashboard without changing the operational school context', function () {
    $dinas = User::factory()->create(['role' => 'dinas']);
    $first = districtSchool('SD Pilihan');
    $second = districtSchool('SMK Lain');
    $second->update(['level' => 'smk', 'ownership' => 'swasta']);
    Student::create(['school_id' => $first->id, 'nis' => 'S1', 'name' => 'Siswa Satu', 'gender' => 'L', 'is_active' => true]);
    Student::create(['school_id' => $second->id, 'nis' => 'S2', 'name' => 'Siswa Dua', 'gender' => 'P', 'is_active' => true]);

    $this->actingAs($dinas)->get('/dinas')->assertOk()
        ->assertViewHas('districtDashboard', fn ($d) => $d['people']['students'] === 2 && $d['schoolBreakdown']['smk_mak'] === 1);
    $this->get('/dinas?school_id='.$first->id)->assertOk()->assertSessionMissing('active_school_id')
        ->assertViewHas('selectedSchool', fn ($s) => $s->id === $first->id)
        ->assertViewHas('districtDashboard', fn ($d) => $d['people']['students'] === 1 && $d['schoolBreakdown']['smk_mak'] === 0);
    $this->get('/dinas')->assertViewHas('selectedSchool', null);
});

it('omits all finance data when permission is absent', function () {
    $this->travelTo(now()->setDate(2026, 10, 26));
    $school = districtSchool('Tidak Mengizinkan');
    districtExpense($school, 987654321);

    $this->actingAs(User::factory()->create(['role' => 'dinas']))->get('/dinas')
        ->assertOk()->assertViewHas('districtDashboard', fn ($d) => ! array_key_exists('bos', $d) && ! $d['finance']['available'])
        ->assertDontSee('987654321');
});

it('aggregates only approved BOS expenses of consenting active schools and immediately honors revocation', function () {
    $this->travelTo(now()->setDate(2026, 10, 26));
    $allowed = districtSchool('Mengizinkan', true);
    $denied = districtSchool('Tidak Mengizinkan');
    $inactive = districtSchool('Nonaktif', true);
    $inactive->update(['status' => 'suspended']);
    districtExpense($allowed, 100);
    districtExpense($allowed, 50, 'approved', 'BOS Reguler');
    districtExpense($allowed, 700, 'pending');
    districtExpense($allowed, 800, 'rejected');
    districtExpense($allowed, 900, 'approved', 'Komite');
    districtExpense($denied, 99999);
    districtExpense($inactive, 88888);

    $this->actingAs(User::factory()->create(['role' => 'dinas']))->get('/dinas')->assertOk()
        ->assertViewHas('districtDashboard', fn ($d) => $d['bos'] === [0.0, 0.0, 0.0, 0.0, 0.0, 150.0]
            && $d['finance']['schoolCount'] === 1 && $d['finance']['totalSchoolCount'] === 2);
    $this->get('/dinas?school_id='.$denied->id)
        ->assertViewHas('districtDashboard', fn ($d) => ! array_key_exists('bos', $d));
    $allowed->dinas_finance_allowed = false;
    $allowed->save();
    $this->get('/dinas')->assertViewHas('districtDashboard', fn ($d) => ! array_key_exists('bos', $d));
});

it('rejects selecting an unavailable school', function (string $state) {
    $school = districtSchool('Tidak Tersedia');
    $state === 'inactive' ? $school->update(['is_active' => false]) : $school->update(['status' => 'suspended']);
    $this->actingAs(User::factory()->create(['role' => 'dinas']))->get('/dinas?school_id='.$school->id)->assertNotFound();
})->with(['inactive', 'suspended']);

it('keeps an empty monitored school set empty even when historical BOS expenses exist', function () {
    $school = districtSchool('Nonaktif', true);
    $school->update(['is_active' => false]);
    districtExpense($school, 99999);

    $this->actingAs(User::factory()->create(['role' => 'dinas']))->get('/dinas')->assertOk()
        ->assertViewHas('districtDashboard', fn ($d) => $d['people']['students'] === 0 && ! array_key_exists('bos', $d)
            && $d['finance']['totalSchoolCount'] === 0);
});

it('scopes teacher and staff attendance to the selected school with six calendar months at month end', function () {
    $this->travelTo(now()->setDate(2026, 10, 31));
    $first = districtSchool('Sekolah Pertama');
    $second = districtSchool('Sekolah Kedua');
    foreach ([$first, $second] as $school) {
        foreach (['Guru', 'Staf TU'] as $role) {
            $teacher = Teacher::create(['school_id' => $school->id, 'name' => $role, 'role_type' => $role, 'is_active' => true]);
            TeacherAttendance::create(['school_id' => $school->id, 'teacher_id' => $teacher->id, 'attendance_date' => '2026-10-10', 'status' => 'H']);
            TeacherAttendance::create(['school_id' => $school->id, 'teacher_id' => $teacher->id, 'attendance_date' => '2026-10-11', 'status' => 'S']);
            TeacherAttendance::create(['school_id' => $school->id, 'teacher_id' => $teacher->id, 'attendance_date' => '2026-05-01', 'status' => 'H']);
        }
    }

    $this->actingAs(User::factory()->create(['role' => 'dinas']))->get('/dinas?school_id='.$first->id)
        ->assertOk()->assertViewHas('districtDashboard', fn ($d) => $d['months'] === ['May 2026', 'Jun 2026', 'Jul 2026', 'Aug 2026', 'Sep 2026', 'Oct 2026']
            && $d['attendance']['teachers'] === [1, 0, 0, 0, 0, 1] && $d['attendance']['staff'] === [1, 0, 0, 0, 0, 1]);
    $this->get('/dinas')->assertViewHas('districtDashboard', fn ($d) => $d['attendance']['teachers'] === [2, 0, 0, 0, 0, 2]);
});

it('rejects malformed consent without changing the existing permission', function () {
    $school = districtSchool('Sekolah Kepala', true);
    $head = User::factory()->create(['role' => 'kepsek']);
    districtRole($head, $school, 'kepsek');
    $this->actingAs($head)->withSession(['active_school_id' => $school->id])->from('/settings/dinas-access')
        ->put('/settings/dinas-access', ['dinas_finance_allowed' => 'invalid'])->assertSessionHasErrors('dinas_finance_allowed');
    expect($school->fresh()->dinas_finance_allowed)->toBeTrue();
});

it('allows Dinas to update only its own profile and ignores injected role or target fields', function () {
    $dinas = User::factory()->create(['role' => 'dinas']);
    $other = User::factory()->create(['name' => 'Pengguna Lain']);
    $this->actingAs($dinas)->get('/profile')->assertOk();
    $this->from('/profile')->post('/profile', ['fname' => 'Dinas', 'lname' => 'Baru', 'email' => 'dinas.baru@example.test',
        'password' => 'new-password', 'password_confirmation' => 'new-password', 'user_id' => $other->id,
        'role' => 'super_admin', 'dinas_finance_allowed' => true])->assertRedirect('/profile')->assertSessionHasNoErrors();

    expect($dinas->fresh()->name)->toBe('Dinas Baru')->and($dinas->fresh()->role)->toBe('dinas')
        ->and(Hash::check('new-password', $dinas->fresh()->password))->toBeTrue()
        ->and($other->fresh()->name)->toBe('Pengguna Lain');
    $this->delete('/profile')->assertStatus(405);
});
