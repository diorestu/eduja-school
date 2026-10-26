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
            'lifecycleHistories' => \Illuminate\Support\Facades\DB::table('lifecycle_histories')->where('school_id', $schoolId)->where('subject_type', 'student')->orderByDesc('id')->get()->groupBy('subject_id'),
            'title' => 'Data Murid',
            'students' => $students,
            'classes' => $classes,
            'linkableAccounts' => \App\Models\User::whereHas('schoolRoles', fn ($q) => $q->where('school_id', $schoolId)->where('role', 'siswa')->where('is_active', true)->where('membership_status', 'active'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, SchoolContext $schoolContext, AcademicLifecycleService $service)
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);

        if ($request->input('action') === 'photo') {
            $validated = $request->validate([
                'student_id' => ['required', 'integer', Rule::exists('students', 'id')->where('school_id', $schoolId)],
                'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            ]);
            $student = Student::where('school_id', $schoolId)->findOrFail($validated['student_id']);
            $student->update(['photo_path' => $request->file('photo')->store('student-photos/'.$schoolId, 'local')]);

            return back()->with('success', 'Foto murid berhasil diperbarui.');
        }
        if ($request->input('action') === 'generation') {
            $validated = $request->validate(['student_id' => ['required', 'integer'], 'generation' => ['nullable', 'integer', 'between:1900,2200']]);
            Student::where('school_id', $schoolId)->findOrFail($validated['student_id'])->update(['generation' => $validated['generation']]);

            return back()->with('success', 'Angkatan murid berhasil diperbarui.');
        }
        if ($request->input('action') === 'status') {
            $validated = $request->validate([
                'student_id' => ['required', 'integer'],
                'status' => ['required', Rule::in(Student::STATUSES)],
                'status_date' => ['required', 'date'],
                'status_note' => ['required_if:status,dropped_out', 'nullable', 'string', 'max:1000'],
                'transfer_destination' => ['required_if:status,transferred', 'nullable', 'string', 'max:255'],
                'transfer_document' => ['required_if:status,transferred', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
                'status_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ]);
            $person = Student::where('school_id', $schoolId)->findOrFail($validated['student_id']);
            $storedPaths = [];
            foreach (['transfer_document', 'status_document'] as $field) {
                if ($request->hasFile($field)) {
                    $storedPaths[$field.'_path'] = $request->file($field)->store('lifecycle/'.$schoolId.'/student/'.$person->id, 'local');
                }
            }
            try {
            $service->transitionStudent(
                $schoolId,
                $validated['student_id'],
                $validated['status'],
                $validated['status_date'],
                $validated['status_note'] ?? null,
                array_filter([
                    'transfer_destination' => $validated['transfer_destination'] ?? null,
                    ...$storedPaths,
                ]),
                $request->user()?->id,
            );

            } catch (\Throwable $exception) {
                foreach ($storedPaths as $path) \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
                throw $exception;
            }
            return back()->with('success', 'Status murid berhasil diperbarui.');
        }

        $editing = $request->input('action') === 'update';
        $existingStudent = $editing ? Student::where('school_id', $schoolId)->findOrFail($request->integer('student_id')) : null;
        $validated = $request->validate([
            'nis' => ['required', 'string', Rule::unique('students', 'nis')->ignore($existingStudent?->id)],
            'nisn' => ['nullable', 'string', Rule::unique('students', 'nisn')->ignore($existingStudent?->id)],
            'name' => ['required', 'string'],
            'gender' => ['required', 'in:L,P'],
            'phone' => ['nullable', 'string'],
            'parent_name' => ['nullable', 'string'],
            'parent_phone' => ['nullable', 'string'],
            'generation' => ['nullable', 'integer', 'between:1900,2200'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'school_class_id' => [$editing ? 'nullable' : 'required', 'integer'],
            'user_id' => ['nullable', 'integer', Rule::exists('school_user_roles', 'user_id')->where('school_id', $schoolId)->where('role', 'siswa')->where('is_active', true)->where('membership_status', 'active'), Rule::unique('students', 'user_id')->where('school_id', $schoolId)->ignore($existingStudent?->id)],
        ]);
        if (isset($validated['school_class_id'])) abort_unless(SchoolClass::where('school_id', $schoolId)->whereKey($validated['school_class_id'])->exists(), 404);
        unset($validated['photo']);
        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store('student-photos/'.$schoolId, 'local');
        }

        if ($existingStudent) {
            unset($validated['school_class_id']);
            $existingStudent->update($validated);
            return back()->with('success', 'Data murid berhasil diperbarui.');
        }

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

        return redirect()->route('siswa.index')->with('success', 'Murid berhasil ditambahkan.');
    }
    public function lifecycleDocument(int $student, int $history, string $kind, SchoolContext $context)
    {
        abort_unless(in_array($kind, ['transfer_document_path', 'status_document_path'], true), 404);
        $record = \Illuminate\Support\Facades\DB::table('lifecycle_histories')->where('id', $history)
            ->where('school_id', $context->activeSchoolIdFor())->where('subject_type', 'student')->where('subject_id', $student)->first();
        abort_unless($record, 404);
        $path = (json_decode($record->metadata ?? '{}', true) ?? [])[$kind] ?? null;
        abort_unless($path && \Illuminate\Support\Facades\Storage::disk('local')->exists($path), 404);
        return \Illuminate\Support\Facades\Storage::disk('local')->download($path);
    }

}
