<?php

use App\Models\Alumni;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function documentMember(School $school, string $role): User
{
    $user = User::factory()->create(['role' => $role]);
    $school->roles()->create(['user_id' => $user->id, 'role' => $role, 'is_active' => true, 'membership_status' => 'active']);

    return $user;
}

it('keeps a foundation without active affiliations empty', function () {
    $foreign = School::create(['name' => 'Sekolah Di Luar Yayasan', 'is_active' => true, 'status' => 'active']);
    Student::create(['school_id' => $foreign->id, 'nis' => 'F1', 'name' => 'Murid Rahasia', 'gender' => 'L']);
    $foundation = User::factory()->create(['role' => 'yayasan']);
    $foreign->roles()->create(['user_id' => $foundation->id, 'role' => 'yayasan', 'is_active' => true, 'membership_status' => 'pending']);

    $this->actingAs($foundation)->get('/yayasan')->assertOk()
        ->assertViewHas('metrics', fn ($metrics) => collect($metrics)->firstWhere('label', 'Sekolah')['value'] === 0
            && collect($metrics)->firstWhere('label', 'Siswa')['value'] === 0);
    $this->get('/yayasan/sekolah/'.$foreign->id)->assertForbidden();
});

it('derives the alumni job server side for education and gap year statuses', function (string $status, ?string $job) {
    $school = School::create(['name' => 'Sekolah Alumni', 'is_active' => true, 'status' => 'active']);
    $user = documentMember($school, 'alumni');
    $alumni = Alumni::create(['school_id' => $school->id, 'user_id' => $user->id, 'name' => 'Nama Tetap', 'nisn' => 'NISN-TETAP']);

    $this->actingAs($user)->withSession(['active_school_id' => $school->id])->put('/alumni/'.$alumni->id, [
        'current_status' => $status, 'current_job' => 'Nilai buatan client', 'village' => 'Kelurahan A', 'city' => 'Makassar', 'province' => 'Sulawesi Selatan',
        'name' => 'Diubah', 'nisn' => 'PALSU',
    ])->assertSessionHasNoErrors();
    expect($alumni->fresh()->current_job)->toBe($job)->and($alumni->fresh()->village)->toBe('Kelurahan A')
        ->and($alumni->fresh()->name)->toBe('Nama Tetap')->and($alumni->fresh()->nisn)->toBe('NISN-TETAP');
})->with([['Melanjutkan Pendidikan', 'Pelajar'], ['Kuliah', 'Mahasiswa'], ['Gap year', null]]);

it('requires an occupation for working statuses and rejects an unknown status', function (string $status) {
    $school = School::create(['name' => 'Sekolah Alumni', 'is_active' => true, 'status' => 'active']);
    $user = documentMember($school, 'alumni');
    $alumni = Alumni::create(['school_id' => $school->id, 'user_id' => $user->id, 'name' => 'Alumni']);
    $this->actingAs($user)->withSession(['active_school_id' => $school->id])->from('/alumni')
        ->put('/alumni/'.$alumni->id, ['current_status' => $status])
        ->assertSessionHasErrors(in_array($status, ['Bekerja', 'Kuliah sambil bekerja', 'Wirausaha'], true) ? 'current_job' : 'current_status');
})->with(['Bekerja', 'Kuliah sambil bekerja', 'Wirausaha', 'Status bebas']);

it('shows official school education separately from validated manual history', function () {
    $school = School::create(['name' => 'SMA Sistem', 'is_active' => true, 'status' => 'active']);
    $user = documentMember($school, 'alumni');
    $student = Student::create(['school_id' => $school->id, 'user_id' => $user->id, 'name' => 'Alumni', 'nis' => 'A1', 'gender' => 'L', 'generation' => 2020]);
    $alumni = Alumni::create(['school_id' => $school->id, 'student_id' => $student->id, 'user_id' => $user->id, 'name' => 'Alumni', 'graduation_year' => 2023]);
    $this->actingAs($user)->withSession(['active_school_id' => $school->id]);
    $this->get('/alumni')->assertOk()->assertSee('SMA Sistem')->assertSee('2020');
    $this->put('/alumni/'.$alumni->id, ['current_status' => 'Kuliah', 'education_entries' => [
        ['institution_name' => 'Universitas Mandiri', 'department' => 'Informatika', 'entry_year' => 2023, 'graduation_year' => null, 'source' => 'school'],
    ]])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('alumni_education_histories', ['alumni_id' => $alumni->id, 'institution_name' => 'SMA Sistem', 'source' => 'school', 'entry_year' => 2020]);
    $this->assertDatabaseHas('alumni_education_histories', ['alumni_id' => $alumni->id, 'institution_name' => 'Universitas Mandiri', 'source' => 'manual']);
    $this->put('/alumni/'.$alumni->id, ['current_status' => 'Kuliah', 'education_entries' => []])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('alumni_education_histories', 1);
    $this->put('/alumni/'.$alumni->id, ['current_status' => 'Kuliah', 'education_entries' => [
        ['institution_name' => 'Tahun Salah', 'entry_year' => 2025, 'graduation_year' => 2020],
    ]])->assertSessionHasErrors('education_entries.0.graduation_year');
});
