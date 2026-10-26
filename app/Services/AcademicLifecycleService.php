<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Alumni;
use App\Models\ClassStudent;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicLifecycleService
{
    public const STUDENT_STATUSES = ['active', 'graduated', 'transferred', 'dropped_out', 'deceased'];

    public const TEACHER_STATUSES = ['active', 'resigned', 'retired', 'contract_ended', 'deceased'];

    public function createAcademicYear(int $schoolId, array $attributes): AcademicYear
    {
        return DB::transaction(function () use ($schoolId, $attributes) {
            if ($attributes['is_active'] ?? false) {
                AcademicYear::where('school_id', $schoolId)->update(['is_active' => false]);
            }

            return AcademicYear::create([
                'school_id' => $schoolId,
                'year' => $attributes['year'],
                'semester' => $attributes['semester'],
                'start_date' => $attributes['start_date'],
                'end_date' => $attributes['end_date'],
                'is_active' => (bool) ($attributes['is_active'] ?? false),
            ]);
        });
    }

    public function activateAcademicYear(int $schoolId, int $academicYearId): AcademicYear
    {
        return DB::transaction(function () use ($schoolId, $academicYearId) {
            $academicYear = AcademicYear::where('school_id', $schoolId)->findOrFail($academicYearId);
            AcademicYear::where('school_id', $schoolId)->update(['is_active' => false]);
            $academicYear->update(['is_active' => true]);

            return $academicYear->refresh();
        });
    }

    public function createRombels(int $schoolId, array $attributes): Collection
    {
        $school = School::findOrFail($schoolId);
        $academicYear = AcademicYear::where('school_id', $schoolId)->findOrFail($attributes['academic_year_id']);
        $departmentId = $attributes['department_id'] ?? null;

        if (in_array($school->level, ['smk', 'mak'], true)) {
            abort_unless($departmentId, 422, 'Jurusan wajib dipilih untuk SMK/MAK.');
            $department = DB::table('departments')
                ->where('school_id', $schoolId)
                ->where('is_active', true)
                ->find($departmentId);
            abort_unless($department, 404);
        } else {
            $department = null;
            $departmentId = null;
        }

        $count = (int) ($attributes['rombel_count'] ?? 1);
        $names = filled($attributes['name'] ?? null)
            ? [$attributes['name']]
            : collect(range(1, $count))->map(fn (int $number) => $this->rombelName(
                $school->level,
                (int) $attributes['grade'],
                $department?->code,
                $number,
            ))->all();

        return collect($names)->map(fn (string $name) => SchoolClass::create([
            'school_id' => $schoolId,
            'name' => $name,
            'grade' => $attributes['grade'],
            'department_id' => $departmentId,
            'academic_year_id' => $academicYear->id,
            'teacher_id' => $attributes['teacher_id'] ?? null,
        ]));
    }

    public function promote(int $schoolId, array $studentIds, int $targetClassId, ?int $changedBy = null): int
    {
        return DB::transaction(function () use ($schoolId, $studentIds, $targetClassId, $changedBy) {
            $targetClass = SchoolClass::where('school_id', $schoolId)->findOrFail($targetClassId);
            $students = Student::where('school_id', $schoolId)
                ->where('status', 'active')
                ->whereIn('id', $studentIds)
                ->lockForUpdate()->get();
            $this->assertSelection($students, $studentIds);

            foreach ($students as $student) {
                $currentMappings = ClassStudent::where('student_id', $student->id)
                    ->whereHas('schoolClass', fn ($query) => $query
                        ->where('school_id', $schoolId)
                    )
                    ->with('schoolClass')
                    ->get();

                if ($currentMappings->isEmpty() || $currentMappings->contains(fn ($mapping) => (int) $targetClass->grade !== (int) $mapping->schoolClass->grade + 1 || (int) $targetClass->id === (int) $mapping->schoolClass->id)) {
                    throw ValidationException::withMessages(['target_class_id' => 'Pilih kelas satu tingkat di atas pada tahun ajaran berikutnya.']);
                }
                $targetStart = $targetClass->academicYear?->start_date;
                if ($targetStart && $currentMappings->contains(fn ($mapping) => $mapping->schoolClass->academicYear?->start_date && $targetStart->lessThanOrEqualTo($mapping->schoolClass->academicYear->start_date))) {
                    throw ValidationException::withMessages(['target_class_id' => 'Tahun ajaran tujuan harus setelah tahun ajaran asal.']);
                }
                foreach ($currentMappings as $mapping) {
                    $this->archiveEnrollment($schoolId, $student->id, $mapping->schoolClass, 'promoted', $changedBy);
                    $mapping->delete();
                }

                ClassStudent::updateOrCreate([
                    'student_id' => $student->id,
                    'school_class_id' => $targetClass->id,
                ]);
            }

            return $students->count();
        });
    }

    public function graduate(int $schoolId, array $studentIds, string $graduationDate, int $graduationYear, ?int $changedBy = null): int
    {
        return DB::transaction(function () use ($schoolId, $studentIds, $graduationDate, $graduationYear, $changedBy) {
            $students = Student::where('school_id', $schoolId)
                ->where('status', 'active')
                ->whereIn('id', $studentIds)
                ->with('schoolClasses.department')
                ->lockForUpdate()->get();

            $this->assertSelection($students, $studentIds);
            $terminalGrade = match (School::findOrFail($schoolId)->level) {
                'sd', 'mi' => 6,
                'smp', 'mts' => 9,
                default => 12,
            };
            foreach ($students as $student) {
                if (! $student->schoolClasses->contains(fn ($class) => (int) $class->grade === $terminalGrade)) {
                    throw ValidationException::withMessages(['student_ids' => 'Kelulusan hanya untuk siswa kelas akhir.']);
                }
            }

            foreach ($students as $student) {
                $this->notifyOutstandingBills($student, false);
                $class = $student->schoolClasses->sortByDesc(fn (SchoolClass $item) => $item->academic_year_id)->first();
                $this->transition(
                    $student,
                    'student',
                    'graduated',
                    $graduationDate,
                    'Lulus tahun '.$graduationYear,
                    ['graduation_year' => $graduationYear],
                    $changedBy,
                );

                $alumni = Alumni::updateOrCreate(
                    ['school_id' => $schoolId, 'student_id' => $student->id],
                    [
                        'name' => $student->name,
                        'user_id' => $student->user_id,
                        'nisn' => $student->nisn,
                        'graduation_year' => $graduationYear,
                        'department_name' => $class?->department?->name,
                        'phone' => $student->phone,
                        'current_status' => 'Lulus',
                    ],
                );

                app(AlumniProfileService::class)->synchronizeSchoolHistory($alumni);

                if ($student->user_id) {
                    DB::table('school_user_roles')->updateOrInsert(
                        ['school_id' => $schoolId, 'user_id' => $student->user_id, 'role' => 'alumni'],
                        ['is_active' => true, 'membership_status' => 'active', 'lifecycle_suspended_by' => null, 'created_at' => now(), 'updated_at' => now()],
                    );
                    app(NotificationService::class)->notifyUsers($schoolId, [$student->user_id], 'alumni.welcome',
                        'Anda telah lulus. Lengkapi profil dan riwayat alumni.', route('alumni.index'), ['student_id' => $student->id], 'alumni-welcome:'.$student->id.':'.$graduationDate);
                }

                $mappings = ClassStudent::where('student_id', $student->id)
                    ->whereHas('schoolClass', fn ($query) => $query->where('school_id', $schoolId))
                    ->with('schoolClass')
                    ->get();
                foreach ($mappings as $mapping) {
                    $this->archiveEnrollment($schoolId, $student->id, $mapping->schoolClass, 'graduated', $changedBy);
                    $mapping->delete();
                }
            }

            return $students->count();
        });
    }

    public function transitionStudent(int $schoolId, int $studentId, string $status, ?string $date, ?string $reason, array $metadata = [], ?int $changedBy = null): Student
    {
        abort_unless(in_array($status, self::STUDENT_STATUSES, true), 422, 'Status siswa tidak valid.');

        return DB::transaction(function () use ($schoolId, $studentId, $status, $date, $reason, $metadata, $changedBy) {
            $student = Student::where('school_id', $schoolId)->lockForUpdate()->findOrFail($studentId);
            if ($status === 'graduated') {
                $this->graduate($schoolId, [$studentId], $date ?? now()->toDateString(), (int) substr($date ?? now()->toDateString(), 0, 4), $changedBy);
                return $student->refresh();
            }
            if ($status !== 'active' && $student->status !== 'active') {
                throw ValidationException::withMessages(['status' => 'Siswa harus aktif sebelum dinonaktifkan.']);
            }
            if ($status === 'transferred' && (blank($metadata['transfer_destination'] ?? null) || blank($metadata['transfer_document_path'] ?? null))) {
                throw ValidationException::withMessages(['transfer_destination' => 'Sekolah tujuan dan surat pindah wajib diisi.']);
            }
            if ($status === 'dropped_out' && blank($reason)) {
                throw ValidationException::withMessages(['status_note' => 'Alasan berhenti wajib diisi.']);
            }
            if ($status !== 'active') {
                $this->notifyOutstandingBills($student, true);
            }
            $this->transition($student, 'student', $status, $date, $reason, $metadata, $changedBy);

            return $student->refresh();
        });
    }

    public function transitionTeacher(int $schoolId, int $teacherId, string $status, ?string $date, ?string $reason, array $metadata = [], ?int $changedBy = null): Teacher
    {
        abort_unless(in_array($status, self::TEACHER_STATUSES, true), 422, 'Status guru/tendik tidak valid.');

        return DB::transaction(function () use ($schoolId, $teacherId, $status, $date, $reason, $metadata, $changedBy) {
            $teacher = Teacher::where('school_id', $schoolId)->findOrFail($teacherId);
            $this->transition($teacher, 'teacher', $status, $date, $reason, $metadata, $changedBy);

            return $teacher->refresh();
        });
    }

    private function transition(Model $person, string $subjectType, string $status, ?string $date, ?string $reason, array $metadata, ?int $changedBy): void
    {
        $isActive = $status === 'active';
        $fromStatus = $person->status ?: ($person->is_active ? 'active' : 'inactive');

        $person->update([
            'status' => $status,
            'is_active' => $isActive,
            'status_date' => $date,
            'status_note' => $reason,
        ]);

        DB::table('lifecycle_histories')->insert([
            'school_id' => $person->school_id,
            'subject_type' => $subjectType,
            'subject_id' => $person->id,
            'from_status' => $fromStatus,
            'to_status' => $status,
            'effective_date' => $date,
            'reason' => $reason,
            'metadata' => $metadata === [] ? null : json_encode($metadata),
            'changed_by' => $changedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! $isActive) {
            if ($person instanceof Student) {
                $mappings = ClassStudent::where('student_id', $person->id)
                    ->whereHas('schoolClass', fn ($query) => $query->where('school_id', $person->school_id))
                    ->with('schoolClass')
                    ->get();
                foreach ($mappings as $mapping) {
                    $this->archiveEnrollment($person->school_id, $person->id, $mapping->schoolClass, $status, $changedBy);
                    $mapping->delete();
                }
            }

            $this->suspendSchoolMembership($person, $subjectType);
        } else {
            $this->restoreSchoolMembership($person, $subjectType);
        }
    }

    private function archiveEnrollment(int $schoolId, int $studentId, ?SchoolClass $schoolClass, string $action, ?int $createdBy): void
    {
        if (! $schoolClass) {
            return;
        }

        DB::table('student_class_histories')->insert([
            'school_id' => $schoolId,
            'student_id' => $studentId,
            'school_class_id' => $schoolClass->id,
            'academic_year_id' => $schoolClass->academic_year_id,
            'action' => $action,
            'started_at' => null,
            'ended_at' => now()->toDateString(),
            'metadata' => null,
            'created_by' => $createdBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function suspendSchoolMembership(Model $person, string $subjectType): void
    {
        if (! $person->user_id) {
            return;
        }
        $roles = $subjectType === 'student' ? ['siswa'] : ['guru', 'tendik', 'wali_kelas', 'kepsek', 'wakasek', 'bendahara', 'teller', 'tu', 'staf_tu'];
        DB::table('school_user_roles')->where('user_id', $person->user_id)->where('school_id', $person->school_id)
            ->whereIn('role', $roles)->where('is_active', true)->where('membership_status', 'active')
            ->update(['is_active' => false, 'membership_status' => 'inactive', 'lifecycle_suspended_by' => $subjectType.':'.$person->id, 'updated_at' => now()]);
    }

    private function restoreSchoolMembership(Model $person, string $subjectType): void
    {
        if ($person->user_id) {
            DB::table('school_user_roles')->where('user_id', $person->user_id)->where('school_id', $person->school_id)
                ->where('lifecycle_suspended_by', $subjectType.':'.$person->id)
                ->update(['is_active' => true, 'membership_status' => 'active', 'lifecycle_suspended_by' => null, 'updated_at' => now()]);
        }
    }

    private function assertSelection(Collection $students, array $ids): void
    {
        if ($students->count() !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['student_ids' => 'Pilih siswa aktif yang valid pada sekolah ini.']);
        }
    }

    private function notifyOutstandingBills(Student $student, bool $includeVicePrincipal): void
    {
        if (! DB::table('invoices')->where('school_id', $student->school_id)->where('student_id', $student->id)->where('status', '!=', 'Lunas')->exists()) {
            return;
        }
        $recipients = DB::table('school_user_roles')->where('school_id', $student->school_id)
            ->whereIn('role', $includeVicePrincipal ? ['bendahara', 'wakasek'] : ['bendahara'])
            ->where('is_active', true)->where('membership_status', 'active')->pluck('user_id')->all();
        $title = 'Perubahan status '.$student->name.': masih terdapat tagihan yang perlu ditindaklanjuti.';
        $financeIds = array_values(array_diff($recipients, [$student->guardian_user_id]));
        app(NotificationService::class)->notifyUsers($student->school_id, $financeIds, 'student.outstanding_bills', $title, route('finance.arrears'), ['student_id' => $student->id]);
        if ($student->guardian_user_id) {
            app(NotificationService::class)->notifyUsers($student->school_id, [$student->guardian_user_id], 'student.outstanding_bills', $title, route('portal.orang-tua'), ['student_id' => $student->id]);
        }
    }

    public function deleteUnused(Model $model, string $column, array $tables, string $field): void
    {
        DB::transaction(function () use ($model, $column, $tables, $field) {
            $model->newQuery()->whereKey($model->id)->lockForUpdate()->firstOrFail();
            foreach ($tables as $table) {
                if (\Illuminate\Support\Facades\Schema::hasColumn($table, $column) && DB::table($table)->where($column, $model->id)->exists()) {
                    throw ValidationException::withMessages([$field => 'Data sudah digunakan. Riwayat dan transaksi harus tetap tersimpan.']);
                }
            }
            $model->delete();
        });
    }

    private function rombelName(string $level, int $grade, ?string $departmentCode, int $number): string
    {
        $gradeName = match ($level) {
            'sd', 'mi' => ['I', 'II', 'III', 'IV', 'V', 'VI'][$grade - 1] ?? (string) $grade,
            'smp', 'mts' => ['VII', 'VIII', 'IX'][$grade - 7] ?? (string) $grade,
            default => ['X', 'XI', 'XII'][$grade - 10] ?? (string) $grade,
        };

        return trim($gradeName.' '.($departmentCode ?? '').' '.$number);
    }
}
