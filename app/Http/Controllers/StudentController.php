<?php

namespace App\Http\Controllers;

use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AcademicLifecycleService;
use App\Services\SchoolContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(SchoolContext $schoolContext)
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);
        $students = Student::where('school_id', $schoolId)->with('schoolClasses.academicYear')->orderBy('name')->get();
        $classes = SchoolClass::where('school_id', $schoolId)->with('academicYear')->get();

        return view('pages.kesiswaan.siswa', [
            'title' => 'Data Siswa',
            'students' => $students,
            'classes' => $classes,
        ]);
    }

    public function store(Request $request, SchoolContext $schoolContext, AcademicLifecycleService $service)
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);

        if ($request->input('action') === 'status') {
            $validated = $request->validate([
                'student_id' => ['required', 'integer'],
                'status' => ['required', Rule::in(Student::STATUSES)],
                'status_date' => ['required', 'date'],
                'status_note' => ['nullable', 'string', 'max:1000'],
                'transfer_destination' => ['nullable', 'string', 'max:255'],
                'transfer_document_path' => ['nullable', 'string', 'max:255'],
            ]);
            $service->transitionStudent(
                $schoolId,
                $validated['student_id'],
                $validated['status'],
                $validated['status_date'],
                $validated['status_note'] ?? null,
                array_filter([
                    'transfer_destination' => $validated['transfer_destination'] ?? null,
                    'transfer_document_path' => $validated['transfer_document_path'] ?? null,
                ]),
                $request->user()?->id,
            );

            return back()->with('success', 'Status siswa berhasil diperbarui.');
        }

        $validated = $request->validate([
            'nis' => ['required', 'string', 'unique:students,nis'],
            'nisn' => ['nullable', 'string', 'unique:students,nisn'],
            'name' => ['required', 'string'],
            'gender' => ['required', 'in:L,P'],
            'phone' => ['nullable', 'string'],
            'parent_name' => ['nullable', 'string'],
            'parent_phone' => ['nullable', 'string'],
            'school_class_id' => ['required', 'integer'],
        ]);
        abort_unless(SchoolClass::where('school_id', $schoolId)->whereKey($validated['school_class_id'])->exists(), 404);

        $student = Student::create([
            ...$validated,
            'school_id' => $schoolId,
            'status' => 'active',
            'is_active' => true,
        ]);

        // Assign to Class
        ClassStudent::create([
            'student_id' => $student->id,
            'school_class_id' => $validated['school_class_id'],
        ]);

        return redirect()->route('siswa.index')->with('success', 'Siswa berhasil ditambahkan.');
    }
}
