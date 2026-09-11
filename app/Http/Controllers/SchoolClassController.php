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

        $service->createRombels($schoolId, $validated);

        return redirect()->route('kelas.index')->with('success', 'Kelas berhasil ditambahkan.');
    }
}
