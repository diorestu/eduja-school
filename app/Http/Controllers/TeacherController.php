<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Services\AcademicLifecycleService;
use App\Services\SchoolContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeacherController extends Controller
{
    public function index(SchoolContext $schoolContext)
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);
        $teachers = Teacher::where('school_id', $schoolId)->orderBy('name')->get();

        return view('pages.kesiswaan.guru', [
            'title' => 'Data GTK (Guru & Staf)',
            'teachers' => $teachers,
        ]);
    }

    public function store(Request $request, SchoolContext $schoolContext, AcademicLifecycleService $service)
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);

        if ($request->input('action') === 'status') {
            $validated = $request->validate([
                'teacher_id' => ['required', 'integer'],
                'status' => ['required', Rule::in(Teacher::STATUSES)],
                'status_date' => ['required', 'date'],
                'status_note' => ['nullable', 'string', 'max:1000'],
            ]);
            $service->transitionTeacher(
                $schoolId,
                $validated['teacher_id'],
                $validated['status'],
                $validated['status_date'],
                $validated['status_note'] ?? null,
                [],
                $request->user()?->id,
            );

            return back()->with('success', 'Status guru/tendik berhasil diperbarui.');
        }

        $validated = $request->validate([
            'nuptk' => ['nullable', 'string', 'unique:teachers,nuptk'],
            'nip' => ['nullable', 'string', 'unique:teachers,nip'],
            'name' => ['required', 'string'],
            'role_type' => ['required', 'string'],
            'staff_type' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
        ]);

        Teacher::create([...$validated, 'school_id' => $schoolId, 'status' => 'active', 'is_active' => true]);

        return redirect()->route('guru.index')->with('success', 'GTK berhasil ditambahkan.');
    }
}
