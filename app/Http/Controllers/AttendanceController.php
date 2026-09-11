<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Services\SchoolContext;
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
        ]);
    }

    /**
     * Store student attendance logs.
     */
    public function storeSiswa(Request $request, SchoolContext $schoolContext)
    {
        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'attendance_date' => 'required|date',
            'attendances' => 'required|array',
            'attendances.*' => ['required', Rule::in(['H', 'S', 'I', 'A'])],
            'notes' => 'nullable|array',
            'notes.*' => 'nullable|string|max:255',
        ]);

        $classId = $validated['school_class_id'];
        $date = $validated['attendance_date'];
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);
        abort_unless(SchoolClass::where('school_id', $schoolId)->whereKey($classId)->exists(), 404);
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

        foreach ($validated['attendances'] as $studentId => $status) {
            $note = isset($validated['notes'][$studentId]) ? $validated['notes'][$studentId] : null;

            StudentAttendance::updateOrCreate(
                [
                    'student_id' => $studentId,
                    'attendance_date' => $date,
                ],
                [
                    'school_class_id' => $classId,
                    'school_id' => $schoolId,
                    'status' => $status,
                    'note' => $note,
                ]
            );
        }

        return redirect()->back()->with('success', 'Presensi siswa berhasil disimpan!');
    }

    /**
     * Display teacher/staff attendance entry grid.
     */
    public function gtk(Request $request, SchoolContext $schoolContext)
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);
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
        ]);
    }

    /**
     * Store teacher/staff attendance logs.
     */
    public function storeGtk(Request $request, SchoolContext $schoolContext)
    {
        $validated = $request->validate([
            'attendance_date' => 'required|date',
            'attendances' => 'required|array',
            'attendances.*' => ['required', Rule::in(['H', 'S', 'I', 'A', 'DL'])],
            'notes' => 'nullable|array',
            'notes.*' => 'nullable|string|max:255',
        ]);

        $date = $validated['attendance_date'];
        $schoolId = $schoolContext->activeSchoolIdFor();
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

        foreach ($validated['attendances'] as $teacherId => $status) {
            $note = isset($validated['notes'][$teacherId]) ? $validated['notes'][$teacherId] : null;

            TeacherAttendance::updateOrCreate(
                [
                    'teacher_id' => $teacherId,
                    'school_id' => $schoolId,
                    'attendance_date' => $date,
                ],
                [
                    'status' => $status,
                    'note' => $note,
                ]
            );
        }

        return redirect()->back()->with('success', 'Presensi GTK berhasil disimpan!');
    }
}
