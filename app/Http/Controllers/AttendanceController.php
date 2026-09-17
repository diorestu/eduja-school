<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Services\SchoolContext;
use App\Services\AttendanceCommandService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    /**
     * Display student attendance entry grid.
     */
    public function siswa(Request $request, SchoolContext $schoolContext)
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);
        $classes = SchoolClass::where('school_id', $schoolId)->orderBy('name')->get();
        $request->validate([
            'attendance_date' => ['nullable', 'date_format:Y-m-d'],
            'school_class_id' => ['nullable', Rule::exists('school_classes', 'id')->where('school_id', $schoolId)],
            'period' => ['nullable', 'in:day,week,month,semester'],
        ]);

        $selectedClassId = $request->query('school_class_id');
        $selectedDate = $request->query('attendance_date', Carbon::today()->toDateString());

        $students = collect();
        $existingAttendances = collect();

        if ($selectedClassId) {
            // Get all students enrolled in this class
            $students = Student::where('school_id', $schoolId)->where('status', 'active')->where('is_active', true)
                ->whereHas('schoolClasses', function ($q) use ($selectedClassId) {
                    $q->where('school_classes.id', $selectedClassId)
                        ->where('school_classes.school_id', session('active_school_id'));
                })
                ->orderBy('name')
                ->get();

            // Fetch existing attendances for this date and class
            $existingAttendances = StudentAttendance::where('school_id', $schoolId)
                ->where('school_class_id', $selectedClassId)
                ->whereDate('attendance_date', $selectedDate)
                ->get()
                ->keyBy('student_id');
        }

        return view('pages.kesiswaan.presensi.siswa', [
            'title' => 'Presensi Harian Siswa',
            'classes' => $classes,
            'selectedClassId' => $selectedClassId,
            'selectedDate' => $selectedDate,
            'students' => $students,
            'existingAttendances' => $existingAttendances,
            ...$this->historyData($request, $schoolId, $selectedDate, $selectedClassId),
        ]);
    }

    /**
     * Store student attendance logs.
     */
    public function storeSiswa(Request $request, SchoolContext $schoolContext, AttendanceCommandService $attendance)
    {
        abort_unless(app(\App\Services\AttendanceAccess::class)->canEdit($request->user()), 403);
        $schoolId = $schoolContext->activeSchoolIdFor();
        $validated = $request->validate([
            'school_class_id' => ['required', Rule::exists('school_classes', 'id')->where(fn ($query) => $query->where('school_id', $schoolId))],
            'attendance_date' => 'required|date',
            'attendances' => 'required|array',
            'attendances.*' => ['required', Rule::in(StudentAttendance::STATUSES)],
            'notes' => 'nullable|array',
            'notes.*' => 'nullable|string|max:255',
        ]);

        $classId = $validated['school_class_id'];
        $date = $validated['attendance_date'];
        abort_unless($schoolId, 403);
        $submittedStudentIds = array_map('strval', array_keys($validated['attendances']));
        $validStudentIds = Student::where('school_id', $schoolId)
            ->whereIn('id', $submittedStudentIds)
            ->where('status', 'active')
            ->where('is_active', true)
            ->whereHas('schoolClasses', function ($query) use ($classId) {
                $query->where('school_classes.id', $classId);
            })
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if (array_diff($submittedStudentIds, $validStudentIds)) {
            return redirect()->back()->withErrors([
                'attendances' => 'Data presensi memuat siswa yang tidak terdaftar di kelas ini.',
            ]);
        }

        DB::transaction(function () use ($validated, $attendance, $schoolId, $classId, $date): void {
            foreach ($validated['attendances'] as $studentId => $status) {
                $attributes = [
                    'school_class_id' => $classId,
                    'attendance_date' => $date,
                    'status' => $status,
                ];
                if (array_key_exists($studentId, $validated['notes'] ?? [])) {
                    $attributes['note'] = $validated['notes'][$studentId];
                }
                $attendance->recordStudent($schoolId, (int) $studentId, $attributes);
            }
        });

        return redirect()->back()->with('success', 'Presensi siswa berhasil disimpan!');
    }

    /**
     * Display teacher/staff attendance entry grid.
     */
    public function gtk(Request $request, SchoolContext $schoolContext)
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);
        $request->validate([
            'attendance_date' => ['nullable', 'date_format:Y-m-d'],
            'period' => ['nullable', 'in:day,week,month,semester'],
        ]);
        $selectedDate = $request->query('attendance_date', Carbon::today()->toDateString());

        $teachers = Teacher::where('school_id', $schoolId)->where('status', 'active')->where('is_active', true)->orderBy('name')->get();

        $existingAttendances = TeacherAttendance::where('school_id', $schoolId)
            ->whereDate('attendance_date', $selectedDate)
            ->get()
            ->keyBy('teacher_id');

        return view('pages.kesiswaan.presensi.gtk', [
            'title' => 'Presensi Harian GTK (Guru & Tenaga Kependidikan)',
            'selectedDate' => $selectedDate,
            'teachers' => $teachers,
            'existingAttendances' => $existingAttendances,
            ...$this->historyData($request, $schoolId, $selectedDate, null, true),
        ]);
    }

    /**
     * Store teacher/staff attendance logs.
     */
    public function storeGtk(Request $request, SchoolContext $schoolContext, AttendanceCommandService $attendance)
    {
        abort_unless(app(\App\Services\AttendanceAccess::class)->canEdit($request->user()), 403);
        $schoolId = $schoolContext->activeSchoolIdFor();
        $validated = $request->validate([
            'attendance_date' => 'required|date',
            'attendances' => 'required|array',
            'attendances.*' => ['required', Rule::in(TeacherAttendance::STATUSES)],
            'notes' => 'nullable|array',
            'notes.*' => 'nullable|string|max:255',
        ]);

        $date = $validated['attendance_date'];
        abort_unless($schoolId, 403);
        $submittedTeacherIds = array_map('strval', array_keys($validated['attendances']));
        $validTeacherIds = Teacher::where('school_id', $schoolId)
            ->whereIn('id', $submittedTeacherIds)
            ->where('status', 'active')
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if (array_diff($submittedTeacherIds, $validTeacherIds)) {
            return redirect()->back()->withErrors([
                'attendances' => 'Data presensi memuat GTK yang tidak aktif atau tidak terdaftar.',
            ]);
        }

        DB::transaction(function () use ($validated, $attendance, $schoolId, $date): void {
            foreach ($validated['attendances'] as $teacherId => $status) {
                $attributes = [
                    'attendance_date' => $date,
                    'status' => $status,
                ];
                if (array_key_exists($teacherId, $validated['notes'] ?? [])) {
                    $attributes['note'] = $validated['notes'][$teacherId];
                }
                $attendance->recordTeacher($schoolId, (int) $teacherId, $attributes);
            }
        });

        return redirect()->back()->with('success', 'Presensi GTK berhasil disimpan!');
    }

    private function historyData(Request $request, int $schoolId, string $date, $classId = null, bool $gtk = false): array
    {
        $period = $request->query('period', 'day');
        $anchor = Carbon::parse($date);
        [$start, $end] = match ($period) {
            'week' => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()],
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            'semester' => [
                $anchor->copy()->month($anchor->month <= 6 ? 1 : 7)->startOfMonth(),
                $anchor->copy()->month($anchor->month <= 6 ? 6 : 12)->endOfMonth(),
            ],
            default => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
        };
        $query = ($gtk ? TeacherAttendance::query()->with('teacher') : StudentAttendance::query()->with('student', 'schoolClass'))
            ->where('school_id', $schoolId)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->when(! $gtk && $classId, fn ($q) => $q->where('school_class_id', $classId));
        $totals = (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'canEdit' => app(\App\Services\AttendanceAccess::class)->canEdit($request->user()),
            'groupTotals' => $gtk ? collect(['Guru' => 'Guru', 'Tendik' => 'Tendik'])->mapWithKeys(function ($label, $group) use ($query) {
                $records = (clone $query)->whereHas('teacher', fn ($q) => $group === 'Guru'
                    ? $q->where('role_type', 'Guru')
                    : $q->where('role_type', '!=', 'Guru'));
                return [$label => $records->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')];
            }) : collect(),
            'period' => $period,
            'periodStart' => $start,
            'periodEnd' => $end,
            'totals' => $totals,
            'history' => $query->orderByDesc('attendance_date')->orderByDesc('id')->paginate(25)->withQueryString(),
        ];
    }
}
