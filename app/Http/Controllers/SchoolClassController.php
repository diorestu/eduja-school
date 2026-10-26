<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Services\AcademicLifecycleService;
use App\Services\SchoolContext;
use Illuminate\Http\Request;

class SchoolClassController extends Controller
{
    public function index(SchoolContext $schoolContext)
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);
        $classes = SchoolClass::where('school_id', $schoolId)->with(['academicYear', 'teacher', 'department'])->orderBy('name')->get();
        $academicYears = AcademicYear::where('school_id', $schoolId)->orderByDesc('year')->get();
        $teachers = Teacher::where('school_id', $schoolId)->active()->orderBy('name')->get();
        $departments = Department::where('school_id', $schoolId)->where('is_active', true)->orderBy('name')->get();
        $school = School::findOrFail($schoolId);

        return view('pages.kesiswaan.kelas', [
            'title' => 'Data Kelas',
            'classes' => $classes,
            'academicYears' => $academicYears,
            'teachers' => $teachers,
            'departments' => $departments,
            'schoolLevel' => $school->level,
        ]);
    }

    public function store(Request $request, SchoolContext $schoolContext, AcademicLifecycleService $service)
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);
        if ($request->input('action') === 'delete') {
            $data = $request->validate(['class_id' => ['required', 'integer']]);
            $class = SchoolClass::where('school_id', $schoolId)->findOrFail($data['class_id']);
            $service->deleteUnused($class, 'school_class_id', ['class_students', 'student_attendances', 'spp_tariffs', 'student_class_histories', 'learning_materials'], 'class_id');
            return back()->with('success', 'Kelas yang belum digunakan dihapus.');
        }
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'grade' => ['required', 'integer', 'min:1', 'max:12'],
            'academic_year_id' => ['required', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'rombel_count' => ['nullable', 'integer', 'min:1', 'max:50'],
            'teacher_id' => ['nullable', 'integer'],
        ]);

        if (! filled($validated['name'] ?? null) && ! isset($validated['rombel_count'])) {
            $validated['rombel_count'] = 1;
        }

        if (! filled($validated['name'] ?? null) && in_array(School::findOrFail($schoolId)->level, ['smk', 'mak'], true) && ! isset($validated['department_id'])) {
            return back()->withErrors(['department_id' => 'Jurusan wajib dipilih untuk SMK/MAK.'])->withInput();
        }

        if (isset($validated['teacher_id'])) {
            abort_unless(Teacher::where('school_id', $schoolId)->active()->where('id', $validated['teacher_id'])->exists(), 404);
        }

        $level = School::findOrFail($schoolId)->level;
        $allowedGrades = match ($level) { 'sd', 'mi' => range(1, 6), 'smp', 'mts' => range(7, 9), default => range(10, 12) };
        if (! in_array((int) $validated['grade'], $allowedGrades, true)) {
            return back()->withErrors(['grade' => 'Tingkat tidak sesuai jenjang sekolah.'])->withInput();
        }
        if ($request->input('action') === 'update') {
            $data = $request->validate(['class_id' => ['required', 'integer'], 'name' => ['required', 'string', 'max:120']]);
            $class = SchoolClass::where('school_id', $schoolId)->findOrFail($data['class_id']);
            AcademicYear::where('school_id', $schoolId)->findOrFail($validated['academic_year_id']);
            if (in_array($level, ['smk', 'mak'], true)) {
                Department::where('school_id', $schoolId)->where('is_active', true)->findOrFail($validated['department_id'] ?? 0);
            } else {
                $validated['department_id'] = null;
            }
            $class->update(collect($validated)->except('rombel_count')->all());
            return back()->with('success', 'Kelas berhasil diperbarui.');
        }
        $service->createRombels($schoolId, $validated);

        return redirect()->route('kelas.index')->with('success', 'Kelas berhasil ditambahkan.');
    }
}
