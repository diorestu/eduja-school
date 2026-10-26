<?php

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AcademicLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function documentSchool(): int
{
    return DB::table('schools')->insertGetId(['name' => 'Sekolah Dokumen', 'level' => 'sma', 'ownership' => 'swasta', 'status' => 'active', 'is_active' => true]);
}
function documentYearClass(int $school, int $grade = 12): array
{
    $year = DB::table('academic_years')->insertGetId(['school_id' => $school, 'year' => '2026/2027', 'semester' => 'Genap', 'is_active' => true]);
    $class = DB::table('school_classes')->insertGetId(['school_id' => $school, 'academic_year_id' => $year, 'grade' => $grade, 'name' => 'Kelas '.$grade]);
    return [$year, $class];
}
function documentStudent(int $school, int $class): Student
{
    $student = Student::create(['school_id' => $school, 'name' => 'Siswa Dokumen', 'nis' => fake()->unique()->numerify('DOC####'), 'gender' => 'P', 'status' => 'active', 'is_active' => true]);
    DB::table('class_students')->insert(['student_id' => $student->id, 'school_class_id' => $class]);
    return $student;
}
function documentActor(int $school): User
{
    $actor = User::factory()->create(['role' => 'staf_tu']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $actor->id, 'role' => 'staf_tu', 'is_active' => true, 'membership_status' => 'active']);
    return $actor;
}

it('graduates a debtor through individual status and retains their invoice', function () {
    $school = documentSchool(); [$year, $class] = documentYearClass($school);
    $student = documentStudent($school, $class);
    DB::table('invoices')->insert(['school_id' => $school, 'academic_year_id' => $year, 'student_id' => $student->id, 'invoice_number' => 'DOC-INV', 'due_date' => '2027-01-01', 'total_amount' => 100, 'status' => 'Belum Lunas']);
    app(AcademicLifecycleService::class)->transitionStudent($school, $student->id, 'graduated', '2027-06-30', null);
    expect(DB::table('alumni')->where('student_id', $student->id)->exists())->toBeTrue();
    expect($student->fresh()->status)->toBe('graduated');
    expect(DB::table('invoices')->where('student_id', $student->id)->value('status'))->toBe('Belum Lunas');
});

it('rejects graduation below the terminal grade without losing enrollment', function () {
    $school = documentSchool(); [, $class] = documentYearClass($school, 10);
    $student = documentStudent($school, $class);
    try { app(AcademicLifecycleService::class)->graduate($school, [$student->id], '2027-06-30', 2027); }
    catch (\Illuminate\Validation\ValidationException $e) { expect($student->fresh()->status)->toBe('active'); return; }
    $this->fail('Non-final students must not graduate.');
});

it('requires the transfer letter and destination and validates dropout reasons', function () {
    Storage::fake('local'); $school = documentSchool(); [, $class] = documentYearClass($school);
    $student = documentStudent($school, $class); $actor = documentActor($school);
    $this->actingAs($actor)->withSession(['active_school_id' => $school])->post('/siswa', ['action' => 'status', 'student_id' => $student->id, 'status' => 'transferred', 'status_date' => '2027-01-01'])->assertSessionHasErrors(['transfer_destination', 'transfer_document']);
    $this->post('/siswa', ['action' => 'status', 'student_id' => $student->id, 'status' => 'dropped_out', 'status_date' => '2027-01-01'])->assertSessionHasErrors('status_note');
    $this->post('/siswa', ['action' => 'status', 'student_id' => $student->id, 'status' => 'transferred', 'status_date' => '2027-01-01', 'transfer_destination' => 'Sekolah Tujuan', 'transfer_document' => UploadedFile::fake()->create('pindah.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
    $metadata = json_decode(DB::table('lifecycle_histories')->where('subject_id', $student->id)->value('metadata'), true);
    Storage::disk('local')->assertExists($metadata['transfer_document_path']);
});

it('renews a teacher contract without suspending access and requires dates', function () {
    $school = documentSchool(); $actor = documentActor($school);
    $teacher = Teacher::create(['school_id' => $school, 'name' => 'Guru', 'role_type' => 'Guru', 'is_active' => true, 'status' => 'active']);
    $base = ['action' => 'status', 'teacher_id' => $teacher->id, 'status' => 'contract_ended', 'status_date' => '2027-01-01', 'contract_renewed' => 1];
    $this->actingAs($actor)->withSession(['active_school_id' => $school])->post('/guru', $base)->assertSessionHasErrors(['contract_start_date', 'contract_end_date']);
    $this->post('/guru', [...$base, 'contract_start_date' => '2027-01-02', 'contract_end_date' => '2028-01-01'])->assertSessionHasNoErrors();
    expect($teacher->fresh()->status)->toBe('active');
    $metadata = json_decode(DB::table('lifecycle_histories')->where('subject_type', 'teacher')->where('subject_id', $teacher->id)->value('metadata'), true);
    expect($metadata['contract_end_date'])->toBe('2028-01-01');
});

it('reactivates only memberships suspended by that subject and preserves ended roles', function () {
    $school = documentSchool(); $account = User::factory()->create();
    $teacher = Teacher::create(['school_id' => $school, 'user_id' => $account->id, 'name' => 'Guru', 'role_type' => 'Guru', 'is_active' => true, 'status' => 'active']);
    foreach (['guru' => true, 'kepsek' => false] as $role => $active) DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $account->id, 'role' => $role, 'is_active' => $active, 'membership_status' => $active ? 'active' : 'inactive']);
    $service = app(AcademicLifecycleService::class);
    $service->transitionTeacher($school, $teacher->id, 'resigned', '2027-01-01', null);
    $service->transitionTeacher($school, $teacher->id, 'active', '2027-02-01', null);
    expect((bool) DB::table('school_user_roles')->where('user_id', $account->id)->where('role', 'kepsek')->value('is_active'))->toBeFalse();
    expect((bool) DB::table('school_user_roles')->where('user_id', $account->id)->where('role', 'guru')->value('is_active'))->toBeTrue();
});

it('records appointments with private SK and requires ending before replacement', function () {
    Storage::fake('local'); $school = documentSchool(); $actor = documentActor($school); $account = User::factory()->create();
    $teacher = Teacher::create(['school_id' => $school, 'user_id' => $account->id, 'name' => 'Guru', 'role_type' => 'Guru', 'is_active' => true, 'status' => 'active']);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $account->id, 'role' => 'guru', 'is_active' => true, 'membership_status' => 'active']);
    $base = ['action' => 'appoint', 'teacher_id' => $teacher->id, 'role' => 'wakasek', 'appointment_number' => 'SK/1', 'starts_on' => '2027-01-01', 'appointing_institution' => 'Yayasan', 'appointment_document' => UploadedFile::fake()->create('sk.pdf', 10, 'application/pdf')];
    $this->actingAs($actor)->withSession(['active_school_id' => $school])->post('/guru', $base)->assertSessionHasNoErrors();
    expect(DB::table('staff_appointments')->where('teacher_id', $teacher->id)->where('role', 'wakasek')->exists())->toBeTrue();
    $this->post('/guru', [...$base, 'role' => 'bendahara'])->assertSessionHasErrors('role');
    $this->post('/guru', ['action' => 'end_appointment', 'teacher_id' => $teacher->id, 'ends_on' => '2027-06-30'])->assertSessionHasNoErrors();
    expect((bool) DB::table('school_user_roles')->where('user_id', $account->id)->where('role', 'wakasek')->value('is_active'))->toBeFalse();
    expect((bool) DB::table('school_user_roles')->where('user_id', $account->id)->where('role', 'guru')->value('is_active'))->toBeTrue();
});

it('edits classes in school scope and refuses deletion of recorded history', function () {
    $school = documentSchool(); [$year, $class] = documentYearClass($school); $actor = documentActor($school);
    $student = documentStudent($school, $class);
    $this->actingAs($actor)->withSession(['active_school_id' => $school])->post('/kelas', ['action' => 'update', 'class_id' => $class, 'name' => 'XII Baru', 'grade' => 12, 'academic_year_id' => $year])->assertSessionHasNoErrors();
    expect(DB::table('school_classes')->where('id', $class)->value('name'))->toBe('XII Baru');
    $this->post('/kelas', ['action' => 'delete', 'class_id' => $class])->assertSessionHasErrors('class_id');
    expect(DB::table('class_students')->where('student_id', $student->id)->exists())->toBeTrue();
    $other = documentSchool(); [$otherYear, $otherClass] = documentYearClass($other);
    $this->post('/kelas', ['action' => 'update', 'class_id' => $otherClass, 'name' => 'Forged', 'grade' => 12, 'academic_year_id' => $year])->assertNotFound();
});

it('edits academic dates and refuses deleting a year used by invoices or classes', function () {
    $school = documentSchool(); [$year] = documentYearClass($school); $actor = documentActor($school);
    $this->actingAs($actor)->withSession(['active_school_id' => $school])->post('/akademik', ['action' => 'update', 'academic_year_id' => $year, 'year' => '2027/2028', 'semester' => 'Genap', 'start_date' => '2027-01-01', 'end_date' => '2027-06-30'])->assertSessionHasNoErrors();
    expect(DB::table('academic_years')->where('id', $year)->value('year'))->toBe('2027/2028');
    $this->post('/akademik', ['action' => 'delete', 'academic_year_id' => $year])->assertSessionHasErrors('academic_year_id');
    expect(DB::table('academic_years')->where('id', $year)->exists())->toBeTrue();
});

it('edits and deactivates vocational departments without deleting class references', function () {
    $school = documentSchool(); DB::table('schools')->where('id', $school)->update(['level' => 'smk']); $actor = documentActor($school);
    $department = DB::table('departments')->insertGetId(['school_id' => $school, 'code' => 'TKJ', 'name' => 'Lama', 'is_active' => true]);
    $this->actingAs($actor)->withSession(['active_school_id' => $school])->post('/academic/departments', ['action' => 'update', 'department_id' => $department, 'code' => 'TKJ', 'name' => 'Teknik Jaringan'])->assertSessionHasNoErrors();
    expect(DB::table('departments')->where('id', $department)->value('name'))->toBe('Teknik Jaringan');
    $this->post('/academic/departments', ['action' => 'deactivate', 'department_id' => $department])->assertSessionHasNoErrors();
    expect((bool) DB::table('departments')->where('id', $department)->value('is_active'))->toBeFalse();
});

it('links edited people only to eligible accounts in the active school', function () {
    $school = documentSchool(); [, $class] = documentYearClass($school); $actor = documentActor($school);
    $student = documentStudent($school, $class); $account = User::factory()->create(); $other = User::factory()->create();
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $account->id, 'role' => 'siswa', 'is_active' => true, 'membership_status' => 'active']);
    $data = ['action' => 'update', 'student_id' => $student->id, 'nis' => $student->nis, 'name' => 'Nama Baru', 'gender' => 'P', 'user_id' => $account->id];
    $this->actingAs($actor)->withSession(['active_school_id' => $school])->post('/siswa', $data)->assertSessionHasNoErrors();
    expect($student->fresh()->user_id)->toBe($account->id);
    $this->post('/siswa', [...$data, 'user_id' => $other->id])->assertSessionHasErrors('user_id');
    expect($student->fresh()->user_id)->toBe($account->id);
});

it('renders the academic and staff management screens with the new controls', function () {
    $school = documentSchool(); [, $class] = documentYearClass($school); $actor = documentActor($school);
    documentStudent($school, $class);
    Teacher::create(['school_id' => $school, 'name' => 'Guru Render', 'role_type' => 'Guru', 'is_active' => true, 'status' => 'active']);
    $this->actingAs($actor)->withSession(['active_school_id' => $school]);
    foreach (['/siswa', '/guru', '/kelas', '/akademik', '/academic/promotion', '/academic/graduation'] as $url) $this->get($url)->assertOk();
});

it('notifies finance and guardian about graduation debt and invites the new alumnus', function () {
    $school = documentSchool(); [$year, $class] = documentYearClass($school);
    $student = documentStudent($school, $class); $studentUser = User::factory()->create(); $guardian = User::factory()->create(); $finance = User::factory()->create();
    foreach ([[$studentUser, 'siswa'], [$guardian, 'orang_tua'], [$finance, 'bendahara']] as [$account, $role]) DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $account->id, 'role' => $role, 'is_active' => true, 'membership_status' => 'active']);
    $student->update(['user_id' => $studentUser->id, 'guardian_user_id' => $guardian->id, 'generation' => 2024]);
    DB::table('invoices')->insert(['school_id' => $school, 'academic_year_id' => $year, 'student_id' => $student->id, 'invoice_number' => 'NOTIFY-INV', 'due_date' => '2027-01-01', 'total_amount' => 100, 'status' => 'Belum Lunas']);
    app(AcademicLifecycleService::class)->graduate($school, [$student->id], '2027-06-30', 2027);
    expect(DB::table('school_notifications')->where('user_id', $finance->id)->where('type', 'student.outstanding_bills')->exists())->toBeTrue();
    expect(DB::table('school_notifications')->where('user_id', $guardian->id)->where('type', 'student.outstanding_bills')->exists())->toBeTrue();
    expect(DB::table('school_notifications')->where('user_id', $studentUser->id)->where('type', 'alumni.welcome')->exists())->toBeTrue();
    expect(DB::table('alumni_education_histories')->where('entry_year', 2024)->where('source_school_id', $school)->exists())->toBeTrue();
});

it('rejects promotion to the same class and preserves the current enrollment', function () {
    $school = documentSchool(); [, $class] = documentYearClass($school, 10); $student = documentStudent($school, $class);
    try { app(AcademicLifecycleService::class)->promote($school, [$student->id], $class); }
    catch (\Illuminate\Validation\ValidationException $e) { expect(DB::table('class_students')->where('student_id', $student->id)->where('school_class_id', $class)->exists())->toBeTrue(); return; }
    $this->fail('Promotion to the current grade/year must be rejected.');
});

it('protects student and staff private documents across schools', function () {
    Storage::fake('local'); $school = documentSchool(); [, $class] = documentYearClass($school); $student = documentStudent($school, $class); $actor = documentActor($school);
    Storage::disk('local')->put('private/pindah.pdf', '%PDF-private');
    $history = DB::table('lifecycle_histories')->insertGetId(['school_id' => $school, 'subject_type' => 'student', 'subject_id' => $student->id, 'to_status' => 'transferred', 'metadata' => json_encode(['transfer_document_path' => 'private/pindah.pdf'])]);
    $this->actingAs($actor)->withSession(['active_school_id' => $school])->get(route('siswa.lifecycle-document', ['student' => $student->id, 'history' => $history, 'kind' => 'transfer_document_path']))->assertOk();
    $otherSchool = documentSchool(); $other = documentActor($otherSchool);
    $this->actingAs($other)->withSession(['active_school_id' => $otherSchool])->get(route('siswa.lifecycle-document', ['student' => $student->id, 'history' => $history, 'kind' => 'transfer_document_path']))->assertNotFound();
});

it('requires ending an existing legacy management role before a new appointment', function () {
    Storage::fake('local'); $school = documentSchool(); $actor = documentActor($school); $account = User::factory()->create();
    $teacher = Teacher::create(['school_id' => $school, 'user_id' => $account->id, 'name' => 'Guru Lama', 'role_type' => 'Guru', 'status' => 'active', 'is_active' => true]);
    DB::table('school_user_roles')->insert(['school_id' => $school, 'user_id' => $account->id, 'role' => 'wakasek', 'is_active' => true, 'membership_status' => 'active']);
    $base = ['action' => 'appoint', 'teacher_id' => $teacher->id, 'role' => 'bendahara', 'appointment_number' => 'SK/2', 'starts_on' => '2027-07-01', 'appointing_institution' => 'Yayasan', 'appointment_document' => UploadedFile::fake()->create('sk.pdf', 10, 'application/pdf')];
    $this->actingAs($actor)->withSession(['active_school_id' => $school])->post('/guru', $base)->assertSessionHasErrors('role');
    $this->post('/guru', ['action' => 'end_appointment', 'teacher_id' => $teacher->id, 'role' => 'wakasek', 'ends_on' => '2027-06-30'])->assertSessionHasNoErrors();
    $this->post('/guru', $base)->assertSessionHasNoErrors();
    expect((bool) DB::table('school_user_roles')->where('user_id', $account->id)->where('role', 'wakasek')->value('is_active'))->toBeFalse();
});
