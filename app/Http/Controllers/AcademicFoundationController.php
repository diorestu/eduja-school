<?php

namespace App\Http\Controllers;

use App\Models\Alumni;
use App\Models\Department;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AcademicLifecycleService;
use App\Services\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcademicFoundationController extends Controller
{
    public function departments(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();

        abort_unless($schoolId, 403);
        abort_unless(in_array($schoolContext->activeSchool()?->level, ['smk', 'mak'], true), 403);
        return view('pages.academic.departments', ['title' => 'Jurusan & Program Keahlian', 'departments' => Department::where('school_id', $schoolId)->orderBy('code')->get()]);
    }

    public function storeDepartment(Request $request, SchoolContext $schoolContext, AcademicLifecycleService $service): RedirectResponse
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);
        if (! in_array($schoolContext->activeSchool()?->level, ['smk', 'mak'], true)) return back()->withErrors(['code' => 'Jurusan hanya tersedia untuk SMK/MAK.']);
        if (in_array($request->input('action'), ['deactivate', 'activate'], true)) {
            $data = $request->validate(['department_id' => ['required', 'integer']]);
            Department::where('school_id', $schoolId)->findOrFail($data['department_id'])->update(['is_active' => $request->input('action') === 'activate']);
            return back()->with('success', 'Status jurusan diperbarui; riwayat kelas tetap tersimpan.');
        }
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);
        if (! in_array(optional($schoolContext->activeSchool())->level, ['smk', 'mak'], true)) {
            return back()->withErrors(['code' => 'Jurusan hanya tersedia untuk SMK/MAK.'])->withInput();
        }
        if ($request->input('action') === 'update') {
            $data = $request->validate(['department_id' => ['required', 'integer']]);
            $department = Department::where('school_id', $schoolId)->findOrFail($data['department_id']);
            if (Department::where('school_id', $schoolId)->where('code', strtoupper($validated['code']))->whereKeyNot($department->id)->exists()) return back()->withErrors(['code' => 'Kode jurusan sudah digunakan.']);
            $department->update(['code' => strtoupper($validated['code']), 'name' => $validated['name']]);
            return back()->with('success', 'Jurusan diperbarui.');
        }
        Department::updateOrCreate(
            ['school_id' => $schoolId, 'code' => strtoupper($validated['code'])],
            ['name' => $validated['name'], 'is_active' => true],
        );

        return back()->with('success', 'Jurusan berhasil disimpan.');
    }

    public function promotion(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);
        $years = \App\Models\AcademicYear::where('school_id', $schoolId)->orderByDesc('year')->get();
        $yearId = request()->integer('academic_year_id') ?: $years->firstWhere('is_active', true)?->id;
        $classes = SchoolClass::where('school_id', $schoolId)->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))->orderBy('grade')->orderBy('name')->get();
        $terminalGrade = match ($schoolContext->activeSchool()?->level) { 'sd', 'mi' => 6, 'smp', 'mts' => 9, default => 12 };
        $sourceClasses = $classes;
        if (request()->user()->hasRole('wali_kelas') && ! request()->user()->hasRole(['kepsek', 'wakasek', 'tu', 'staf_tu', 'pic_sekolah'])) {
            $sourceClasses = $sourceClasses->filter(fn ($class) => $class->teacher?->user_id === request()->user()->id);
        }
        $sourceId = request()->integer('source_class_id');
        $students = $sourceClasses->contains('id', $sourceId)
            ? Student::where('school_id', $schoolId)->active()->whereHas('schoolClasses', fn ($q) => $q->where('school_classes.id', $sourceId))->orderBy('name')->get()
            : collect();
        return view('pages.academic.workflow', [
            'title' => 'Kenaikan Kelas', 'workflow' => 'promotion', 'years' => $years, 'yearId' => $yearId,
            'classes' => $sourceClasses, 'sourceId' => $sourceId, 'students' => $students,
            'targetClasses' => SchoolClass::where('school_id', $schoolId)->with('academicYear')->orderBy('grade')->orderBy('name')->get(),
        ]);
    }

    public function promote(Request $request, SchoolContext $schoolContext, AcademicLifecycleService $service): RedirectResponse
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'distinct'],
            'academic_year_id' => ['nullable', 'integer'],
            'source_class_id' => ['nullable', 'integer'],
            'target_class_id' => ['required', 'integer'],
        ]);

        $this->validateAcademicSelection($schoolId, $validated, false);
        $count = $service->promote($schoolId, $validated['student_ids'], $validated['target_class_id'], $request->user()?->id);

        if ($count === 0) {
            return back()->with('error', 'Tidak ada siswa aktif yang valid untuk diproses.');
        }

        $targetClass = SchoolClass::where('school_id', $schoolId)->findOrFail($validated['target_class_id']);

        return back()->with('success', $count.' siswa berhasil dipindahkan ke '.$targetClass->name.'.');
    }

    public function graduation(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($schoolId, 403);
        $years = \App\Models\AcademicYear::where('school_id', $schoolId)->orderByDesc('year')->get();
        $yearId = request()->integer('academic_year_id') ?: $years->firstWhere('is_active', true)?->id;
        $classes = SchoolClass::where('school_id', $schoolId)->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))->orderBy('grade')->orderBy('name')->get();
        $terminalGrade = match ($schoolContext->activeSchool()?->level) { 'sd', 'mi' => 6, 'smp', 'mts' => 9, default => 12 };
        $sourceClasses = $classes->where('grade', $terminalGrade);
        if (request()->user()->hasRole('wali_kelas') && ! request()->user()->hasRole(['kepsek', 'wakasek', 'tu', 'staf_tu', 'pic_sekolah'])) {
            $sourceClasses = $sourceClasses->filter(fn ($class) => $class->teacher?->user_id === request()->user()->id);
        }
        $sourceId = request()->integer('source_class_id');
        $students = $sourceClasses->contains('id', $sourceId)
            ? Student::where('school_id', $schoolId)->active()->whereHas('schoolClasses', fn ($q) => $q->where('school_classes.id', $sourceId))->orderBy('name')->get()
            : collect();
        return view('pages.academic.workflow', [
            'title' => 'Kelulusan', 'workflow' => 'graduation', 'years' => $years, 'yearId' => $yearId,
            'classes' => $sourceClasses, 'sourceId' => $sourceId, 'students' => $students,
            'targetClasses' => SchoolClass::where('school_id', $schoolId)->with('academicYear')->orderBy('grade')->orderBy('name')->get(),
        ]);
    }

    public function graduate(Request $request, SchoolContext $schoolContext, AcademicLifecycleService $service): RedirectResponse
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'distinct'],
            'academic_year_id' => ['nullable', 'integer'],
            'source_class_id' => ['nullable', 'integer'],
            'graduation_date' => ['required', 'date'],
            'graduation_year' => ['required', 'integer', 'min:1900', 'max:2100'],
        ]);

        $this->validateAcademicSelection($schoolId, $validated, true);
        $count = $service->graduate(
            $schoolId, $validated['student_ids'], $validated['graduation_date'],
            $validated['graduation_year'], $request->user()?->id,
        );

        if ($count === 0) {
            return back()->with('error', 'Tidak ada siswa aktif yang valid untuk diproses.');
        }

        return back()->with('success', $count.' siswa berhasil diluluskan dan dipindahkan ke alumni.');
    }

    private function validateAcademicSelection(int $schoolId, array $data, bool $graduation): void
    {
        $source = isset($data['source_class_id']) ? SchoolClass::where('school_id', $schoolId)->findOrFail($data['source_class_id']) : null;
        if (isset($data['academic_year_id'])) {
            \App\Models\AcademicYear::where('school_id', $schoolId)->findOrFail($data['academic_year_id']);
            if ($source && (int) $source->academic_year_id !== (int) $data['academic_year_id']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['source_class_id' => 'Kelas tidak termasuk tahun ajaran yang dipilih.']);
            }
        }
        $isHomeroomOnly = request()->user()->hasRole('wali_kelas') && ! request()->user()->hasRole(['kepsek', 'wakasek', 'tu', 'staf_tu', 'pic_sekolah']);
        if ($isHomeroomOnly) {
            abort_unless($source && $source->teacher?->user_id === request()->user()->id, 403);
        }
        if ($source) {
            $count = Student::where('school_id', $schoolId)->active()->whereIn('id', $data['student_ids'])
                ->whereHas('schoolClasses', fn ($q) => $q->where('school_classes.id', $source->id))->count();
            if ($count !== count($data['student_ids'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['student_ids' => 'Siswa harus berasal dari kelas yang dipilih.']);
            }
        }
    }

    public function alumni(SchoolContext $schoolContext): View
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        $isAlumni = request()->user()->hasRole(['alumni']);
        $alumni = Alumni::where('school_id', $schoolId)
            ->when($isAlumni, fn ($q) => $q->where('user_id', request()->user()->id))->latest()->get();

        foreach ($alumni as $profile) {
            app(\App\Services\AlumniProfileService::class)->synchronizeSchoolHistory($profile);
            $profile->load('educationEntries');
        }

        return view('pages.foundation.alumni', [
            'title' => 'Alumni',
            'eyebrow' => 'Tracer Study',
            'description' => 'Profil alumni, pendidikan lanjutan, pekerjaan, dan pembaruan tracer study.',
            'alumni' => $alumni,
            'isAlumni' => $isAlumni,
            'editableAlumni' => $isAlumni ? $alumni->firstWhere('user_id', request()->user()->id) : null,
            'linkableUsers' => ! $isAlumni && request()->user()->hasRole(['super_admin', 'kepsek', 'pic_sekolah', 'tu', 'staf_tu'])
                ? \App\Models\User::whereHas('schoolRoles', fn ($q) => $q->where('school_id', $schoolId)->where('role', 'alumni')->where('is_active', true)->where('membership_status', 'active'))->orderBy('name')->get(['id', 'name', 'email'])
                : collect(),
        ]);
    }

    public function updateAlumni(Request $request, Alumni $alumni, SchoolContext $schoolContext): RedirectResponse
    {
        $schoolId = $schoolContext->activeSchoolIdFor();
        abort_unless($alumni->school_id === $schoolId, 404);

        if ($request->user()->hasRole(['alumni'])) {
            abort_unless($alumni->user_id === $request->user()->id, 403);
        }

        app(\App\Services\AlumniProfileService::class)->update($alumni, $request);

        return back()->with('success', 'Profil alumni berhasil diperbarui.');
    }

    public function linkAlumni(Request $request, Alumni $alumni, SchoolContext $context): RedirectResponse
    {
        $schoolId = $context->activeSchoolIdFor();
        abort_unless((int) $alumni->school_id === $schoolId, 404);
        abort_unless($request->user()->hasRole(['super_admin', 'kepsek', 'pic_sekolah', 'tu', 'staf_tu'])
            && ! $request->user()->hasRole('alumni'), 403);
        $data = $request->validate(['user_id' => ['required', 'integer']]);
        $account = \App\Models\User::whereKey($data['user_id'])
            ->whereHas('schoolRoles', fn ($q) => $q->where('school_id', $schoolId)->where('role', 'alumni')->where('is_active', true)->where('membership_status', 'active'))->first();
        if (! $account) {
            throw \Illuminate\Validation\ValidationException::withMessages(['user_id' => 'Pilih akun alumni aktif pada sekolah ini.']);
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($account, $alumni, $schoolId) {
            \App\Models\User::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $record = Alumni::whereKey($alumni->id)->lockForUpdate()->firstOrFail();
            if ($record->user_id || Alumni::where('school_id', $schoolId)->where('user_id', $account->id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['user_id' => 'Profil atau akun sudah terhubung. Periksa data sebelum mencoba kembali.']);
            }
            $record->update(['user_id' => $account->id]);
        });
        return back()->with('success', 'Akun alumni berhasil dihubungkan.');
    }
}
