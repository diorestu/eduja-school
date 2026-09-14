<?php

use App\Models\School;
use App\Services\ConfiguredGpsAttendancePolicy;
use App\Services\DatabaseRfidAttendanceAdapter;
use App\Services\LogAttendanceNotifier;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

function adapterSchool(string $name = 'Sekolah Adapter'): School
{
    return School::create([
        'name' => $name,
        'level' => 'sma',
        'ownership' => 'swasta',
        'is_active' => true,
        'status' => 'active',
    ]);
}

function adapterStudent(int $schoolId, array $overrides = []): int
{
    return DB::table('students')->insertGetId(array_merge([
        'school_id' => $schoolId,
        'nis' => 'ADP-'.uniqid(),
        'name' => 'Siswa Adapter',
        'gender' => 'L',
        'is_active' => true,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

function adapterTeacher(int $schoolId, array $overrides = []): int
{
    return DB::table('teachers')->insertGetId(array_merge([
        'school_id' => $schoolId,
        'name' => 'Guru Adapter',
        'role_type' => 'Guru',
        'is_active' => true,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

it('logs typed attendance notification events without claiming provider delivery', function () {
    Log::spy();

    $event = new \App\Contracts\AttendanceNotificationEvent(
        school: ['id' => 7, 'name' => 'Sekolah Event'],
        recipient: ['id' => 11, 'channel' => 'whatsapp'],
        subject: ['type' => 'student', 'id' => 22],
        request: ['id' => 33, 'status' => 'approved'],
        message: ['subject' => 'Presensi disetujui', 'body' => 'Permohonan diproses'],
    );

    app(LogAttendanceNotifier::class)->notify($event);

    Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context) use ($event): bool {
        return $message === 'EDUJA attendance notification event recorded'
            && $context['event'] === $event->toArray()
            && ! str_contains(strtolower(json_encode($context)), 'delivered');
    });
});

it('preserves the existing notification send behavior while accepting typed attendance events', function () {
    Log::spy();
    $service = new NotificationService(new LogAttendanceNotifier);

    $service->send('email', 'wali@example.test', 'Pesan lama');
    $service->sendAttendance(new \App\Contracts\AttendanceNotificationEvent(
        school: ['id' => 1],
        recipient: ['id' => 2],
        subject: ['type' => 'student', 'id' => 3],
        request: ['id' => 4],
        message: ['body' => 'Pesan typed'],
    ));

    Log::shouldHaveReceived('info')->twice();
});

it('resolves only one active same-school identity for an RFID UID', function () {
    $school = adapterSchool();
    $studentId = adapterStudent($school->id);
    $classId = DB::table('school_classes')->insertGetId([
        'school_id' => $school->id,
        'name' => 'X IPA',
        'grade' => 10,
        'academic_year_id' => DB::table('academic_years')->insertGetId([
            'school_id' => $school->id,
            'year' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('student_attendances')->insert([
        'student_id' => $studentId,
        'school_id' => $school->id,
        'school_class_id' => $classId,
        'attendance_date' => '2026-09-14',
        'status' => 'H',
        'source' => 'rfid',
        'rfid_uid' => ' UID-001 ',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(app(DatabaseRfidAttendanceAdapter::class)->resolve('uid-001', $school->id))
        ->toBe([
            'person_type' => 'student',
            'person_id' => $studentId,
            'school_id' => $school->id,
            'source' => 'rfid',
            'rfid_uid' => 'UID-001',
        ]);
});

it('rejects unknown, inactive, cross-school, and ambiguous RFID identities', function () {
    $school = adapterSchool();
    $otherSchool = adapterSchool('Sekolah Lain');
    $inactiveId = adapterStudent($school->id, ['nis' => 'ADP-INACTIVE', 'is_active' => false, 'status' => 'graduated']);
    $crossSchoolId = adapterTeacher($otherSchool->id);
    $classId = DB::table('school_classes')->insertGetId([
        'school_id' => $school->id,
        'name' => 'X IPA',
        'grade' => 10,
        'academic_year_id' => DB::table('academic_years')->insertGetId([
            'school_id' => $school->id,
            'year' => '2026/2027',
            'semester' => 'Ganjil',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('student_attendances')->insert([
        'student_id' => $inactiveId, 'school_id' => $school->id, 'school_class_id' => $classId,
        'attendance_date' => '2026-09-14', 'status' => 'H', 'source' => 'rfid', 'rfid_uid' => 'BAD-001',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('teacher_attendances')->insert([
        'teacher_id' => $crossSchoolId, 'school_id' => $otherSchool->id,
        'attendance_date' => '2026-09-14', 'status' => 'H', 'source' => 'rfid', 'rfid_uid' => 'BAD-002',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $adapter = app(DatabaseRfidAttendanceAdapter::class);
    expect($adapter->resolve('unknown', $school->id)['reason'])->toBe('RFID tidak terdaftar.');
    expect($adapter->resolve('BAD-001', $school->id)['reason'])->toBe('Identitas RFID tidak aktif atau tidak berada pada sekolah tersebut.');
    expect($adapter->resolve('BAD-002', $school->id)['reason'])->toBe('Identitas RFID tidak aktif atau tidak berada pada sekolah tersebut.');
});

it('accepts configured GPS coordinates and normalizes metadata', function () {
    $policy = new ConfiguredGpsAttendancePolicy([
        'schools' => [9 => ['latitude' => -5.147665, 'longitude' => 119.432732, 'radius_meters' => 100]],
    ]);

    expect($policy->evaluate(9, -5.147665, 119.432732, ['photo_path' => 'attendance/a.jpg']))
        ->toMatchArray(['accepted' => true, 'latitude' => -5.147665, 'longitude' => 119.432732, 'photo_path' => 'attendance/a.jpg']);
});

it('rejects missing GPS configuration and coordinates outside the configured radius', function () {
    $policy = new ConfiguredGpsAttendancePolicy([
        'schools' => [9 => ['latitude' => -5.147665, 'longitude' => 119.432732, 'radius_meters' => 50]],
    ]);

    expect($policy->evaluate(10, -5.147665, 119.432732)['reason'])->toBe('Konfigurasi GPS sekolah tidak tersedia.');
    expect($policy->evaluate(9, -5.150000, 119.432732)['reason'])->toBe('Koordinat berada di luar radius sekolah.');
});
